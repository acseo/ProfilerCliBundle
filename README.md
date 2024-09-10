# Symfony Profiler CLI Command

<img src="https://socialify.git.ci/acseo/ProfilerCliBundle/image?description=1&font=Inter&language=1&name=1&owner=1&stargazers=1&theme=Auto" alt="ProfilerCliBundle" width="100%" />

This Symfony bundle provides a CLI command that interacts with the Symfony Profiler. It allows users to display the details of profiler tokens, export `curl` commands for HTTP requests, and interact with profiler data in an efficient and flexible way.

## Features

- List recent profiler tokens with detailed information (IP, URL, HTTP method, etc.).
- Display the details of a specific token, including headers, request body, and the generated `curl` command.
- Export the `curl` command for individual tokens or for all retrieved tokens.
- Interact with an easy-to-use interactive menu, or use command-line options for direct access.

## Requirements

- PHP 7.4 or higher
- Symfony 5.x or 6.x
- Symfony Profiler enabled

## Installation

1. Install the bundle via composer:

```bash
composer require acseo/profiler-cli
```

2. Enable the bundle in your bundles.php:

```php
return [
    // Other bundles
    ACSEO\ProfilerCliBundle\ProfilerCliBundle::class => ['dev' => true],
];
```

3. Ensure the Symfony Profiler is enabled in your environment by checking config/packages/dev/web_profiler.yaml:

```yaml
web_profiler:
    toolbar: true
    intercept_redirects: false
```

## Usage

### List Profiler Tokens

By default, the command lists the most recent profiler tokens, displaying their token, IP address, HTTP method, URL, and request time.

```bash
php bin/console acseo:profiler-cli
```

You can filter the tokens by using the following options:

- `--ip`: Filter by IP address
- `--url`: Filter by URL
- `--method`: Filter by HTTP method (e.g., `GET`, `POST`)
- `--limit`: Specify the number of tokens to retrieve (default is `10`)
- `--start`: Specify a start date in `Y-m-d H:i:s` format
- `--end`: Specify an end date in `Y-m-d H:i:s` format

Example:

```bash
php bin/console acseo:profiler-cli --limit=5 --method=POST
```

### Display Token Details

You can directly display the details of a specific token by passing the `--token` option:

```bash
php bin/console acseo:profiler-cli --token=abc123
```

This will display the HTTP request information, including headers, request body, and the generated `curl` command.

### Export curl Command

To export the `curl` command for a specific token, use both the `--token` and `--export` options together:

```bash
php bin/console acseo:profiler-cli --token=abc123 --export
```

This will generate a file in the format `curl_YmdHis-abc123.txt` containing the `curl` command.

You can also export the `curl` commands for all retrieved tokens:

```bash
php bin/console acseo:profiler-cli --export
```

### Interactive Menu

When running the command without options, an interactive menu is displayed. You can navigate the menu using the following keys:

    m: Redisplay the token list
    d: Display the details of a specific token
    e: Export the curl command for the selected token
    q: Quit the program

## Example Use Cases

- Automated Testing: Use the generated curl commands to replay HTTP requests and test API behavior.
- Debugging: Quickly fetch request headers and bodies for analysis.
- Profiling: Filter and analyze specific requests by IP, URL, or HTTP method.

## Contributing

We welcome contributions! Please submit a pull request or open an issue for discussion. Make sure to follow the existing coding style and include unit tests where applicable.

## License

This bundle is licensed under the MIT License. See the LICENSE file for details.