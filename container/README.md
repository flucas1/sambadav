# SambaDAV - Docker

This is a docker container configured to run [SambaDAV](https://github.com/1afa/sambadav)

View on [Docker Hub](https://hub.docker.com/r/menome/sambadav/)

## Instructions

Pull the container with `docker pull menome/sambadav`

The container exposes two mount points:

`/etc/ssl/localcerts/` - this is where you put your certificate and key for Apache2. They should be in PEM format, and named `certificate.pem` and `key.pem`.

`/var/www/htdocs/webfolders/config` - This is where the SambaDAV config files go. View their documentation page for more information.

To run the container without SSL enabled (not recommended) simply specify `SAMBADAV_ENABLE_SSL=false` as an environment variable.
If running the container with SSL, you can upgrade users from HTTP to HTTPS by setting `SAMBADAV_SSL_REDIRECT=true`

## Building the Container

We don't install composer and whatnot inside the docker container, and we don't put SambaDAV releases in this repo.

I've included a shellscript that will run the download and install process with a specified build of SambaDAV.

Run `build_sambadav.sh` before running your docker-build.

You will need to install composer on your local machine, as well as a bunch of PHP dependencies.
