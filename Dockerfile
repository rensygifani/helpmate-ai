# Image untuk deploy HelpMate AI (PHP 8.3 + Apache). Cocok untuk Render, Railway, atau Docker lokal.
FROM php:8.3-apache

# Pengaturan PHP untuk produksi (error tidak ditampilkan ke pengunjung)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/apache.conf /etc/apache2/conf-available/helpmate.conf
RUN a2enconf helpmate

COPY . /var/www/html/
# config.local.php tidak boleh ikut ke image; kunci diatur lewat Environment Variables di hosting
RUN rm -f /var/www/html/config.local.php \
    && chown -R www-data:www-data /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENV PORT=10000
EXPOSE 10000
CMD ["/usr/local/bin/entrypoint.sh"]
