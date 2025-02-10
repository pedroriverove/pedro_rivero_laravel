# Laravel

## Requirements

This uses Laravel 11.x, Please make sure your server meets the requirements before installing.
- PHP >= 8.2
- Composer

## Installation

### Clone the repo and cd into it

```bash
git clone https://github.com/pedroriverove/pedro_rivero_laravel.git
cd pedro_rivero_laravel
```

### Install composer dependencies

```bash
composer install
```

### Create a copy of your .env file

```bash
cp .env.example .env
```

### Set your database credentials in your .env file

Change the following lines in your .env file
```conf
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```

### Generate an app encryption key

```bash
php artisan key:generate
```

### Migrate the database and seed

```bash
php artisan migrate
php artisan db:seed
```

### Microservice setup and base URL configuration

1.  **Install dependencies:**

    In your project's root directory, run:

    ```bash
    npm install
    ```

2.  **Navigate to microservices directory:**

    ```bash
    cd PAY-SERVERS
    ```

3.  **Start microservices (in separate terminals):**

    **Terminal 1:**
    ```bash
    node easy-money.js
    ```

    **Terminal 2:**
    ```bash
    node super-walletz.js
    ```
    
4.  **Configure base URLs:**

    In your `.env` file, set the following:

    ```conf
    EASY_MONEY_BASE_URI=http://localhost:3000
    SUPER_WALLET_BASE_URI=http://localhost:3003
    ```

    **Ensure that the Easymoney and Superwalletrz microservices are running on ports 3000 and 3003 respectively.**

### Run the server

```bash
php artisan serve
```

### Test the API using POSTMAN

You can test the API using the following `curl` command in POSTMAN or any other API testing tool:

```bash
curl --location 'http://127.0.0.1:8000/api/process' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data '{
    "payment_gateway": "EasyMoney",
    "amount": 100,
    "currency": "USD"
}'
```

```bash
curl --location 'http://127.0.0.1:8000/api/process' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data '{
    "payment_gateway": "SuperWalletz",
    "amount": 100,
    "currency": "USD"
}'
```

```bash
curl --location 'http://127.0.0.1:8000/api/webhook/superwalletz/{id}' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data '{
    "transaction_id": "{transaction_id}",
    "status": "success"
}'
```
