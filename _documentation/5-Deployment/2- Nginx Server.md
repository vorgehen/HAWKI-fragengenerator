---
sidebar_position: 2
---


## Server Preparation

To prepare your server, ensure communication ports are properly configured. HAWKI deployment requires the HTTPS protocol,
though testing locally using HTTP is possible but not recommended.

For this guide, we'll assume port usage as follows:
- HTTP: Port 80
- HTTPS: Port 443

Ensure your server meets the following requirements to run Laravel/PHP applications:

1. PHP version 8.2 or higher
2. Required PHP Extensions:
    - PHP >= 8.2
    - Ctype PHP Extension
    - cURL PHP Extension
    - DOM PHP Extension
    - Fileinfo PHP Extension
    - Filter PHP Extension
    - Hash PHP Extension
    - Mbstring PHP Extension
    - OpenSSL PHP Extension
    - PCRE PHP Extension
    - PDO PHP Extension
    - Session PHP Extension
    - Tokenizer PHP Extension
    - XML PHP Extension

Ensure output buffering is enabled in your php.ini file by un-commenting:
output_buffering = 4096
or setting:
output_buffering = On

Additionally, verify that Node and Composer are installed on your machine.


You can use the HAWKI CLI to check if your system meets all requirements. Navigate to project root and run:

```
php hawki check
```

This command checks for:
- PHP version (8.1+)
- Composer 
- Node.js and npm
- Required PHP extensions (mbstring, xml, pdo, curl, zip, json, fileinfo, openssl)

If any dependencies are missing, the command will provide installation instructions.

---
## Nginx Server Configuration

Nginx requires specific configuration to work properly with Laravel applications and WebSocket connections.

1. Install Nginx if not already installed:
```js
sudo apt update
sudo apt install nginx
```


2. Create a new Nginx server block configuration:
sudo nano /etc/nginx/sites-available/hawki

3. Add the following configuration:

```js
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl;
    server_name your-domain.com;

    ssl_certificate /path/to/your/certificate.crt;
    ssl_certificate_key /path/to/your/private.key;

    root /var/www/html/hawki-project/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }

    # WebSocket Proxy for Laravel Reverb
    location /app {
        proxy_pass http://localhost:8080/app;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location /apps {
        proxy_pass http://localhost:8080/apps;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

This configuration creates a redirect from HTTP to HTTPS and sets up the necessary proxy rules for WebSocket connections.

4. Create a symbolic link to enable the site:
```
sudo ln -s /etc/nginx/sites-available/hawki /etc/nginx/sites-enabled/
```

5. Test the Nginx configuration:
```
sudo nginx -t
```

6. Restart Nginx:
```
sudo service nginx restart
```


---
##  Project Deployment Steps

1. Copy the HAWKI project content to the Nginx web server location. This can be done via cloning the git repository or
manually uploading files:
git clone https://github.com/HAWK-Digital-Environments/HAWKI.git /var/www/html/hawki-project

2. Set proper permissions for Laravel:
```
sudo chown -R www-data:www-data /var/www/html/hawki-project
sudo chmod -R 755 /var/www/html/hawki-project/storage
sudo chmod -R 755 /var/www/html/hawki-project/bootstrap/cache
```

3. Install dependency packages by navigating to the project root and executing:
```
cd /var/www/html/hawki-project
composer install
npm install
npm run build
```
> Please note that after changing some variables (for example some of the .env variables) you need to run `npm run build` again to update the build with these variables.


Or use the HAWKI CLI for a streamlined initialization:

```
php hawki init
```

Using `php hawki init` will:
- Create `.env` file from `.env.example`
- Set up required configuration files
- Install Composer dependencies
- Install npm packages
- Create storage symlinks
- Generate application keys and security salts

For a complete guided setup process, use:

```
php hawki init -all
```

4. If using traditional methods, manually generate an application key:
```
php artisan key:generate
```

At this point, the project is transferred to the server, but you may encounter a Laravel error if the database connection is
not configured.

---
##  DATABASE

1. If not already installed, set up a preferred database. This documentation employs MySQL, but selection depends on your
usage and specific requirements.

> ***!!! Please make sure that your database has proper security !!!***

2. Create a new, empty database, such as HAWKI_DB.
3. Update the database connection settings in the .env file with:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1 #Database host IP
DB_PORT=3306 #Database host port
DB_DATABASE=HAWKI_DB #Database name
DB_USERNAME=your_username #Database username
DB_PASSWORD=your_password #Database password
```

You can also use the HAWKI CLI to configure your database settings interactively:

```
php hawki setup -db
```

This interactive command will prompt you for database connection details with sensible defaults.

4. Run database migrations and seed data by navigating to the project directory and executing:
```
php artisan migrate
php artisan db:seed
```

Alternatively, use the HAWKI CLI:

```
php hawki migrate
```

For a fresh database (which will reset all data), use:

```
php hawki migrate --fresh
```

At this stage, the database tables should be set up and operational.

>***IMPORTANT:***
If instructions are not strictly followed, please do not forget to seed the database before allowing any other users to join
HAWKI. This will ensure that the AI Agent (HAWKI) is registered as a user on the database.
If you want start a fresh database run:
```js
php artisan migrate:fresh --seed
```




---
##  PROJECT CONFIGURATION

Edit the .env file within the root folder. Most variables can remain at their default values unless specific adjustments are
required.

You can configure your environment using the HAWKI CLI interactive setup:

```
php hawki setup
```

This command will guide you through configuring all aspects of your HAWKI installation. Alternatively, you can configure specific sections:

```
php hawki setup -g    # General settings
php hawki setup -auth # Authentication settings
php hawki setup -reverb # Reverb settings
```

### Create Storage Link

To allow clients to read files from the storage folder, we need to create a symbolic link for the storage.
Use the following command to create the symbolic link:
```
php artisan storage:link
```

This step is automatically handled if you used `php hawki init`.

You should be able to see the storage shortcut inside public folder.

Please note that after changing the structure of your files in the storage folder you may need to recreate the virtual link:
```
sudo rm -rf public/storage
php artisan storage:link
```

### Setup Server Salts 

For encryption purposes, HAWKI utilises individual salts for each component. Though not mandatory, unique hash keys are
recommended. These can be configured during the HAWKI CLI setup process.

### Setup Authentication Methods

In the .env file find AUTHENTICATION_METHOD variable.
Currently HAWKI supports LDAP, OpenID, and Shibboleth authentication services. A built-in Test User Authentication for
internal testing purposes is also available.

Set the variable to one of the following:
```
- AUTHENTICATION_METHOD="LDAP"
- AUTHENTICATION_METHOD="OIDC"
- AUTHENTICATION_METHOD="Shibboleth"
```

According to your authentication method, set the necessary variables. For more information refer to the documentation in .env
file.

Using HAWKI CLI, you can configure authentication settings with:

```
php hawki setup -auth
```

### Add Data Protection and Imprint

Data protection and imprint notes are linked in the login page. To set your organization specific legal notes:

1. In the .env file find IMPRINT_LOCATION and add the URL to your organization imprint page.
2. Locate DataProtection Files in the /resources/language folder and add the data protection notes for each language in HTML 
format.

### Adding API Keys

Navigate to config folder. There you'll find model_providers.php.example. Rename it to model_providers.php.
Open the file and update the configuration as you need. HAWKI currently supports OpenAI, GWDG, and Google.

You can also use the HAWKI CLI to configure AI model providers interactively:

```
php hawki setup-models
```

This command will allow you to:
- Activate or deactivate AI providers
- Set API keys for each active provider
- Configure the default model
- Set models for system tasks (title generation, prompt improvement, etc.)

Alternatively, edit the configuration file manually:

```js
// The Default Model (use the id of one of model you wish)
'defaultModel' => 'gpt-4o',

// The model which generates the chat names.
'titleGenerationModel' => 'gpt-4o-mini',

'providers' =>[
    'openai' => [
        'id' => 'openai',
        'active' => true, //set to false if you want to disable the provider
        'api_key' => ' ** YOUR SECRET API KEY ** ',
        'api_url' => 'https://api.openai.com/v1/chat/completions',
        'ping_url' => 'https://api.openai.com/v1/models',
        'models' => [
            [
                'id' => 'gpt-4o',
                'label' => 'OpenAI GPT 4o',
                'streamable' => true,
            ],
            ...
        ]
    ],
    ...
]
```


### Broadcasting & Workers

HAWKI uses https://reverb.laravel.com/ for real-time communication between client and server.
In the .env file you simply need to set reverb variables:

```js
REVERB_APP_ID=my-app-id
REVERB_APP_KEY=my-app-key
REVERB_APP_SECRET=my-app-secret

REVERB configuration defaults to port 8080. Use HTTPS for secure communication:

REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

However, to secure the communication between the client and the server you should use https protocol and port 443 for
websocket as well.
Set the variables to:
```js
REVERB_HOST=yourDomain.com // set your domain without any prefixes
REVERB_PORT=443
REVERB_SCHEME=https //reverb scheme must be set to https instead of http.
```
Also add the path to the SSL certificate chain and key path:
```js
SSL_CERTIFICATE="/path/to/certificate.crt"
SSL_CERTIFICATE_KEY="/path/to/private.key"
```

rebuild the node packages to update the config variables:
```
npm run build
```

Ensure Port 8080 is blocked by the server firewall to prevent direct access. The WebSocket proxy configuration added to the
Nginx server block handles the secure redirection.

---
### SERVICES

Before broadcasting messages to users, each message is queued on the server and processed by Laravel workers.
In order to automate the Reverb broadcasting and Laravel workers, we need to create system services:

1. Create a new file for Reverb at `/etc/systemd/system/reverb.service`. Insert this content:

>**Don't forget to update paths: `/var/www/html/hawki-project`**

```
[Unit]
Description=Reverb WebSocket Server
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/html/hawki-project
ExecStart=/usr/bin/php /var/www/html/hawki-project/artisan reverb:start
Restart=always
TimeoutSec=300
LimitNOFILE=4096

[Install]
WantedBy=multi-user.target
```

2. Create a new file for workers at /etc/systemd/system/laravel-worker.service. Insert this content:

>**Don't forget to update paths: `/var/www/html/hawki-project`**

```
[Unit]
Description=Laravel Worker Service
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/html/hawki-project
ExecStart=/usr/bin/php /var/www/html/hawki-project/artisan queue:work --queue=default,mails,message_broadcast --tries=3 --timeout=90

Restart=always
RestartSec=5
TimeoutSec=300
LimitNOFILE=4096

ExecStartPost=/usr/bin/php /var/www/html/hawki-project/artisan queue:restart

StandardOutput=append:/var/www/html/hawki-project/storage/logs/worker.log
StandardError=append:/var/www/html/hawki-project/storage/logs/worker-error.log

[Install]
WantedBy=multi-user.target

```

3. Reload Systemd Manager Configuration:
```
sudo systemctl daemon-reload
```

4. Enable services:
```
sudo systemctl enable reverb.service
sudo systemctl enable laravel-worker.service
```

5. Start the Services:
```
sudo systemctl start reverb.service
sudo systemctl start laravel-worker.service
```

6. Check Status (to ensure they started correctly):
```
sudo systemctl status reverb.service
sudo systemctl status laravel-worker.service
```

Now that workers are running, the queued messages should be successfully broadcasted to the users.

### Cache Management

To clear all Laravel caches (which may be necessary after configuration changes):

```
php hawki clear-cache
```

This clears:
- Configuration cache
- Application cache
- View cache
- Route cache
- Event cache
- Compiled files




---

## Updating to V2.0.1

If you have already implemented HAWKI 2.0 on your machine, there are a few steps needed to update to version 2.0.1.

1- **As usual, update the project content.**

2- **Set the updated .env variables:**

- **BROADCAST_DRIVER**: Similar to `BROADCAST_CONNECTION`, the `BROADCAST_DRIVER` is set to utilize reverb as the broadcasting backend mechanism.

- **ALLOW_USER_TOKEN_CREATION**: In the new update, the admin can decide whether users are allowed to create API tokens for external communication. You can set `ALLOW_EXTERNAL_COMMUNICATION` to true to allow API communication but restrict key generation by setting `ALLOW_USER_TOKEN_CREATION` accordingly. This setup ensures that tokens can only be created by the server admin using the HAWKI CLI with the command `php hawki create-token`. For more information, refer to the [HAWKI CLI Section](/architecture/HAWKI_CLI).

3- If you are using custom icons different from the ones included in the project, move the icons folder from `resources/views/icons` to `/resources/icons` and change the file formats from .blade.php to .svg. Otherwise, you can skip this step and use the icons included in the 2.0.1 project.

4- **Reinstall Composer and Node packages.**    
The new packages include:

- **blade-ui-kit**: This Composer component enhances icon management. As mentioned previously, this library is responsible for loading project icons in the GUI. It also ensures that the component library does not conflict with other libraries that are or will be used in the project.

- **HAWKI CLI**: This custom CLI facilitates frequently used commands for the installation and maintenance of HAWKI. For more information, refer to the [HAWKI CLI Section](/architecture/HAWKI_CLI).

- **Pako**: The new data-flow of the message broadcasting mechanism includes a compression step, where broadcasting data is compressed on the server side using `gzencode` and decompressed using `Pako` on the client side. With this broadcasting system, HAWKI can broadcast larger data packets, such as Google search results generated by Gemini in chat rooms.

To install the packages, run:

```
composer install
npm install
npm run build
```

5- **Migrate the database tables.**

In this version, new attributes have been added to the database:

- **isRemoved**: This attribute is added to the users and members tables. It allows the server to detect if a user profile has been removed. However, after profile removal, shared data such as group memberships and group messages remain connected to the profile, ensuring the integrity and consistency of room messages even if a member is no longer available.

- **Completion**: This property is added in AI-generated conversation messages (private messages). It flags the completion of a message generated by an AI model. If the generation is interrupted (e.g., due to lost connection or aborted generation), the message will be flagged as incomplete.

- **API Type**: This type is added to usage record types. It is used when a message generation request is sent from an external service via the API endpoint.

Finally, remove all cached data from the server to ensure updates are applied correctly:

```
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

At this point, the new version should be ready and running on your machine.



---
## Troubleshooting

**1. Config updates are not applied to the project.**

By default Laravel caches every configuration in the project. Don't forget to clear the cached data after each adjustment.
For example by using:

php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

**2. Styles or Javascript updates are not applied**

If the styles in browser seem incorrect or the changes are not visible on a live server, the issue may be due to the cached
style or scripts in your browser.
Try to hard reload or empty browser cache and reload.
Some changes in javascript may be also cached in view caches. Use php artisan view:clear to clear the cache.

**3. "Failed to fetch Server Salt"**

Clear Config and cache:
php artisan config:clear
php artisan cache:clear

**4. Vite Packages are not loaded. (md is not defined)**

Make sure node packages are built npm run build.
If the problem persists, locate and remove "hot" file in the public folder.

**5. Nginx 502 Bad Gateway Error**

This might occur if PHP-FPM is not running or configured correctly:
```
sudo systemctl status php8.2-fpm
sudo systemctl restart php8.2-fpm
Also check the Nginx error logs for more details:
sudo tail -f /var/log/nginx/error.log
```

**6. WebSocket Connection Failure**

If WebSocket connections fail, ensure the Reverb service is running and the proxy configuration is correct:
```
sudo systemctl status reverb.service
```
Check that your firewall allows connections on port 443 but blocks direct access to port 8080.
