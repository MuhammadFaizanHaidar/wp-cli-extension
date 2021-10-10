# wp-cli-extension
Add external commands to WordPress command line interface.
# Description
WP-CLI’s goal is to offer a complete alternative to the WordPress admin,for any action you might want to perform in the WordPress admin, there should be an equivalent WP-CLI command. A command is an atomic unit of WP-CLI functionality. wp plugin install (doc) is one such command, as is wp plugin activate (doc). Commands are useful to WordPress users because they can offer simple, precise interfaces for performing complex tasks.
WP-CLI supports registering any callable class, function, or closure as a command. WP_CLI::add_command() (doc) is used for both internal and third-party command registration.
This code snippets registers two external commands to WP_CLI which overrides wp-config.php and htaccess file.

# Reason to use WP CLI
WP-CLI is fast and provides a command line interface, we are developing a hosting solution for WordPress users for which we need to update wp-config files and htaccess in the backend without user interaction. So I ended up using wp-cli. Plugin exposes these commands via cli and execute them on server as a response of an API call from our hosting platform.

# Code structure
WP-CLI supports registering any callable class, function, or closure as a command. It reads usage details from the callback’s. WP_CLI::add_command() (doc) is used for both internal and third-party command registration.
But I prefer to use OOP becuase it resolves naming conflicts, code is easy to manage, enhances code readability and increases code reuseability.
