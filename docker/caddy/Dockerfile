FROM caddy:2

COPY Caddyfile /etc/caddy/Caddyfile

WORKDIR /var/www

CMD ["caddy", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
