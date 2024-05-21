
## Project - Digital Wallet

This is an implementation of a digital wallet feature of an applications. Users
will be able to store funds in their wallets, make purchases, receive payments, and transfer
funds to other users. It implements payment services like Paystack and Flutterwave and also manages 
wallet balances and transactions securely.


### Getting Started:
- Clone the repository: git clone [<repository_url>](https://github.com/PinkCode15/digital-wallet-api.git)
- Install dependencies: `composer install`
- Copy the .env.example file and rename it to .env.
- Generate application key: `php artisan key:generate`
- Update the database configuration in the .env file with your database credentials:
- Set up the database: `php artisan migrate`
- Run the server: `php artisan serve`
- Explore the API documentation for endpoint details [Postman Documentation] (https://www.postman.com/subsync-domain/workspace/digital-wallet/collection/8307909-249e14b9-c3ac-4478-b87c-1de86f90c70c?action=share&creator=8307909)

### How to test
- Set up your .env.testing using your .env file
- Run the wallet tests: `php artisan test --env=testing --filter=WalletTest`

### Architecture Digram
This diagram shows the wallet component and its integration with the payment service.


### Author
Stacey Onuora
