#!/bin/bash

export PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin:/root/bin

yum -y install httpd mod_ssl
chkconfig httpd on
service httpd start

# Generate 50x vhosts
yum -y install util-linux-ng
cat >>/etc/httpd/conf/httpd.conf <<EOF
NameVirtualHost *:80
Include /etc/httpd/conf/vhosts/*.conf
EOF
for x in `seq 1 50`; do
  # Generate vhost data
  vuser="$( uuidgen | cut -d- -f1 )"
  vhost="${vuser}.com"

  # Create user & homedir structure
  adduser ${vuser}
  uuidgen | passwd --stdin ${vuser}
  mkdir -p /home/${vuser}/www
  chmod 750 /home/${vuser}/www
  chown ${vuser}:apache /home/${vuser}/www

  # Populate web content?

  # Config vhost in apache
  mkdir -p /etc/httpd/conf/vhosts
  cat >/etc/httpd/conf/vhosts/${vhost}.conf <<EOF
<VirtualHost *:80>
  ServerName ${vhost}
  ServerAlias www.${vhost}
  DocumentRoot /home/${vuser}/www
  CustomLog logs/${vhost}.log combined
  ErrorLog logs/${vhost}.err
</VirtualHost>
EOF
done

# Put some crap in /tmp and /dev/shm
# Compile some stuff in /tmp too - something malicious, so we can see it in 'strings'
# Include IP lists
# Run some of it

# Launch an IRC eggdrop on some rando port

# Launch an SMTP daemon out of /home/???/www/.../.../.../  -something 777
# Listen on alternate port.  Send spam.

# Put old versions of something in webroots
# Stick a wget in the access logs

