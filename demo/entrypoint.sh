#!/bin/sh
set -eu
if [ ! -f config/system/settings.php ]; then
  su -s /bin/sh www-data -c 'php /opt/soca-demo-setup.php'
fi
touch /tmp/soca-app-ready
exec "$@"
