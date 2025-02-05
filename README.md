# Armanet Magento Integration

The **Armanet_Integration** module for Magento 2.4 facilitates seamless integration between Magento and Armanet services, enhancing your e-commerce platform's capabilities.

## Installation

### 1. Install via Composer (Recommended)
Navigate to your Magento root directory, run the following command:

    $ composer require armanet/magento-integration

Enable and install the module:

    $ php bin/magento module:enable Armanet_Integration
    $ php bin/magento setup:upgrade
    $ php bin/magento cache:flush


### 2. Install Manually
Download the module package. Then, extract and upload the contents to:

    $ app/code/Armanet/Integration

Enable and install the module:

    $ php bin/magento module:enable Armanet_Integration
    $ php bin/magento setup:upgrade
    $ php bin/magento cache:flush

## Configuration
* Log in to your Magento Admin Panel.
* Navigate to **Stores > Configuration > Armanet Integration**.
* Configure the API settings and other parameters as required.
* Save the configuration and clear the cache:

```
    $ php bin/magento cache:flush
```


## Troubleshooting
If you experience any issues, check Magento logs:

    $ tail -f var/log/system.log var/log/exception.log

Run the following command to recompile:

    $ php bin/magento setup:di:compile


## Uninstallation
To remove the module, run:

    $ php bin/magento module:disable Armanet_Integration
    $ composer remove armanet/integration
    $ php bin/magento setup:upgrade
    $ php bin/magento cache:flush
