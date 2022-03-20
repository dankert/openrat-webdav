FROM alpine:3.13

LABEL maintainer="Jan Dankert"

# Install packages
RUN apk --update --no-cache add \
    apache2 apache2-http2 curl \
    php7 php7-apache2 php7-session php7-json php7-mbstring php7-dom php7-xml

ENV DAV_ENABLE               = "false"  \
    DAV_CREATE               = "true"  \
    DAV_READONLY             = ""    \
    DAV_EXPOSE_OPENRAT       = "true"  \
    DAV_COMPLIANT_TO_REDMOND = "true"  \
    DAV_REDIRECT_COLLECTIONS_TO_TRAILING_SLASH = "true" \
    DAV_REALM                = "OpenRat CMS WebDAV Login" \
    DAV_ANONYMOUS            = "false"      \
    CMS_HOST                 = "localhost"  \
    CMS_PORT                 = 8000    \
    CMS_USERNAME             = ""      \
    CMS_PASSWORD             = ""      \
    CMS_DATABASE             = ""      \
    CMS_PATH                 = "/"     \
    CMS_MAX_FILE_SIZE        = "1000"  \
    LOG_LEVEL                = "info"  \
    LOG_FILE                 = "/dev/stdout"



# Configuring apache webserver
# - disable access log
# - enable HTTP/2
RUN sed -i '/CustomLog/s/^/#/g'                     /etc/apache2/httpd.conf && \
    # Enable apache modules
    sed -i '/LoadModule http2_module/s/^#//g'       /etc/apache2/httpd.conf && \
    sed -i '/LoadModule rewrite_module/s/^#//g'     /etc/apache2/httpd.conf && \
    # Listening on ports
    sed -i 's/^Listen 80/Listen 8080/g'       /etc/apache2/httpd.conf && \
    chown apache /var/log/apache2 && \
    chown apache /run/apache2     && \
    # Directory configuration
    echo "<Directory \"/var/www/localhost/htdocs\">"   >> /etc/apache2/httpd.conf && \
    echo "  RewriteEngine on"                          >> /etc/apache2/httpd.conf && \
    echo "  RewriteRule ^(.*)$ dav.php$1 [L]"          >> /etc/apache2/httpd.conf && \
    echo "</Directory>"                                >> /etc/apache2/httpd.conf && \
    # Enable HTTP/2
    echo "Protocols h2 h2c http/1.1"    >> /etc/apache2/httpd.conf && \
    echo "H2ModernTLSOnly off"          >> /etc/apache2/httpd.conf

# Copy the application to document root
COPY .  /var/www/localhost/htdocs

# Place configuration in /etc, outside the docroot.
COPY dav.ini /etc/openrat-webdav.ini

# Logfiles are redirected to standard out
RUN ln -sf /dev/stderr /var/log/apache2/error.log

EXPOSE 8080

WORKDIR /var/www/localhost/

USER apache

HEALTHCHECK --interval=10s --timeout=5m --retries=1 CMD curl -f http://localhost:8080/ || exit 1
CMD /usr/sbin/httpd -D FOREGROUND
