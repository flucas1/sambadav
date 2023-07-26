#!/bin/sh

# If we're using SSL, enable the SSL site. Otherwise, don't.
if [ $SAMBADAV_ENABLE_SSL = "true" ]; then 
  a2ensite sambadav-ssl;
else
  a2dissite sambadav-ssl;
fi;

exec apache2-foreground
