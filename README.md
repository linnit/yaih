# YAIH

## Synopsis

YAIH - Yet Another Image Host

## Installation

Clone the repository, create/import the database, create a database user and configure the `.env` file

    git clone https://github.com/linnit/yaih .
    cp .env.example ../.env
    vim ../.env

Install Composer (https://getcomposer.org/doc/00-intro.md) and install the defined dependencies

    php composer.phar install

Access the admin page at /admin
The default username and password:
admin@example.com
admin

Apache Config

Replace `{DocumentRoot}` with your document root

```
<Directory "{DocumentRoot}">
AllowOverride All

RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</Directory>

<Directory "{DocumentRoot}/view">
Require all denied
</Directory>

<Directory "{DocumentRoot}/model">
Require all denied
</Directory>

<Directory "{DocumentRoot}/controller">
Require all denied
</Directory>

<Directory "{DocumentRoot}/vendor">
Require all denied
</Directory>
```

### Dependencies

Only works with PHP 7+ unless required compatibility packages are installed (password-compat) etc.

php-json php-mbstring php-curl php-imagick

## License

This is free and unencumbered software released into the public domain.

Anyone is free to copy, modify, publish, use, compile, sell, or
distribute this software, either in source code form or as a compiled
binary, for any purpose, commercial or non-commercial, and by any
means.

For more information, please refer to <http://unlicense.org/>
