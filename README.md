# Gatherer Content Management System v3

A powerful CMS that aggregates data into it -- not just out.

Designed for the modern web stack, this CMS combines the power of the
Symfony v7, Twig v3, and Bootstrap v5 for one of the most flexible
HTML5 management systems available.

## Minimum Requirements
* PHP >= 8.2 and these PHP extensions (which are installed and enabled by default in most PHP installations):
    * Ctype
    * iconv
    * JSON
    * PCRE
    * Session
    * SimpleXML
    * Tokenizer
    * Tidy
* [Composer](https://getcomposer.org/download/) (PHP dependency manager)
* Nginx or Apache [configured to run PHP](https://symfony.com/doc/current/setup/web_server_configuration.html)
* MariaDB/MySQL. Although most Doctrine supported DBs should work (Postgres, [etc...](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#driver)) if you put in the effort
* (Optional) An RSS/ATOM reader that can share feeds like [Tiny Tiny RSS](https://tt-rss.org/)

To help verify you have the correct requirements you can install the [Symfony CLI](https://symfony.com/download)
and run `symfony check:requirements`

## Installation
Start with setting up your configuration and then choose your method of running the application.
### Config
1. Copy `.env` to `.env.local`. Open `.env.local` in your favorite editor and update the settings to meet your needs.
   A few important ones:
    1. Set `APP_ENV` to `prod`.
    2. Update `APP_SECRET` with something long and random.
        1. With PHP you can do something like `php -r 'echo bin2hex(random_bytes(16)), "\n";'` in a terminal.
        2. With openssl: `openssl rand -hex 16`
    3. Update the `MAILER_DSN` field to point to your SMTP server. [See Symfony docs for syntax and you may have to install additional packages](https://symfony.com/doc/7.4/mailer.html##transport-setup).
    4. Update the `DATABASE_URL` string with the correct [database details](https://symfony.com/doc/7.4/doctrine.html#configuring-the-database).
        1. If using MariaDB be sure to prefix the server version (e.g. `10.5.8-MariaDB`)
        2. You can optionally [encrypt your database password](https://symfony.com/doc/7.4/configuration/secrets.html).
    5. Update `GCMS_ADMIN_TRUSTED_IPS` if you aren't accessing the site from the same machine.

### Classic Method
If you already have a web stack ready to go, you can install the classical way:
1. From the project root run: `composer install --no-dev`
2. Create the database if you haven't already: `php bin/console doctrine:database:create`
3. Run the database migration to create the tables: `php bin/console doctrine:migrations:migrate`
4. Configure your web server root to be the ./public/ directory and make sure PHP is enabled
    - *Hint*: If you're using Apache, you can install `composer require symfony/apache-pack` for an .htaccess file

### Docker Method
A quick way to get started is with Docker:
1. Check and change any config files as necessary under the `docker/` path.
2. Open the `compose.prod.yaml` file and adjust as necessary -- you may add a database or Elasticsearch.
3. Run `docker compose -f compose.prod.yaml --env-file .env.local up -d`

## Administration
1. On your first visit to the site you will be asked to create an admin user and configure the site.
    1. If you get an access denied error you may need to change the `GCMS_ADMIN_TRUSTED_IPS` setting as described in the installation.
2. On future visits you can log in by using the `/login` or `/admin` URLs.

## Further Setup
Not required but recommended.

### RSS/ATOM Gathering
1. If you choose to consume from an RSS or ATOM feed, first configure it in GCMS's admin page.
2. To ingest the feed, run `php bin/console app:gather`
3. It's best to use something like a cronjob to run this periodically.

### ElasticSearch
1. Install and start an ElasticSearch 8+ (or compatible) server
2. Open `.env.local` and update the `ELASTICSEARCH_HOSTS` value with your server(s)
3. Create the indexes: `fos:elastica:create`
4. Populate the indexes (if you have content already): `fos:elastica:populate`

### ReCaptcha
1. Register a site on https://www.google.com/recaptcha/admin
2. Modify your GOOGLE_RECAPTCHA_SITE_KEY and GOOGLE_RECAPTCHA_SECRET config in `.env.local`

## Upgrading
1. Backup your database and the `.env.local` file as a precaution.
2. Copy the new files.
   1. If using git, do a `checkout <tag-name>` of the new version.
   2. If you are using a compressed archive extract to a new dir and copy in your `.env.local`
3. Run `composer install --no-dev`
4. Run the database migration: `php bin/console doctrine:migrations:migrate`

## Develop & Extend
Gatherer CMS uses well established frameworks such as Symfony and Bootstrap at its core.
Thanks to this, there are thousands of extensions and themes from 3rd parties ready to be installed.
Or use the local build environment and make your own.
1. Install dev packages with `composer install`
2. Set `ENV=dev` in your `.env.local` file
3. Install the [Symfony CLI](https://symfony.com/download)
   1. Run `symfony server:start` to start a local dev server
4. GCMS uses [AssetMapper](https://symfony.com/doc/7.4/frontend/asset_mapper.html). Follow the documentation to build assets.
   - *Hint*: You can remove the `public/assets/` directory for real-time updates, but you still need to compile SASS (`sass:build`)
