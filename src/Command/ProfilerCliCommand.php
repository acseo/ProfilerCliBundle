<?php

namespace ACSEO\ProfilerCliBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Profiler\Profiler;

#[AsCommand(
    name: 'acseo:profiler-cli',
    description: 'Profiler CLI for interacting with Symfony Profiler data',
)]
class ProfilerCliCommand extends Command
{
    private array $tokens;
    private ?string $currentToken = null;

    public function __construct(private ?Profiler $profiler)
    {
        parent::__construct();
    }

    /**
     * Configures the command with various options.
     */
    protected function configure(): void
    {
        $this
            ->addOption('ip', null, InputOption::VALUE_OPTIONAL, 'Filter by IP address', '')
            ->addOption('url', null, InputOption::VALUE_OPTIONAL, 'Filter by URL', '')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Number of tokens to retrieve', 10)
            ->addOption('method', null, InputOption::VALUE_OPTIONAL, 'Filter by HTTP method', '')
            ->addOption('start', null, InputOption::VALUE_OPTIONAL, 'Filter from a start date (format: Y-m-d H:i:s)', '')
            ->addOption('end', null, InputOption::VALUE_OPTIONAL, 'Filter until an end date (format: Y-m-d H:i:s)', '')
            ->addOption('token', null, InputOption::VALUE_OPTIONAL, 'Specify a token to display its details directly', null)
            ->addOption('export', null, InputOption::VALUE_NONE, 'Export the curl commands for all tokens without displaying them');
    }

    /**
     * Executes the main command logic.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->clearScreen();

        // Retrieve options from the command line
        $ip = $input->getOption('ip');
        $url = $input->getOption('url');
        $limit = (int)$input->getOption('limit');
        $method = $input->getOption('method');
        $start = $input->getOption('start');
        $end = $input->getOption('end');
        $tokenOption = $input->getOption('token');
        $exportOption = $input->getOption('export');

        // Convert dates to timestamps if they exist
        $startTimestamp = $start ? (new \DateTime($start))->format('U') : '';
        $endTimestamp = $end ? (new \DateTime($end))->format('U') : '';

        // Retrieve tokens from the profiler
        $this->tokens = $this->profiler->find($ip, $url, $limit, $method, $startTimestamp, $endTimestamp);

        // If both --token and --export options are provided, export the curl command for the specified token
        if ($tokenOption && $exportOption) {
            $this->exportTokenCurl($tokenOption, $io);
            return Command::SUCCESS;
        }

        // If the --token option is provided, display the details of the specified token directly
        if ($tokenOption) {
            $this->displayTokenDetails($tokenOption, $io);
            return Command::SUCCESS;
        }

        // If the --export option is provided, generate curl export files for all tokens
        if ($exportOption) {
            $this->exportAllTokens($io);
            return Command::SUCCESS;
        }

        // Otherwise, display the tokens and start the interactive menu
        $this->displayTokens($io);
        $this->runInteractiveMenu($io);

        return Command::SUCCESS;
    }

    /**
     * Displays the list of tokens.
     */
    private function displayTokens(SymfonyStyle $io): void
    {
        $this->currentToken = null;

        // Display tokens in a table format
        $io->table(
            ['Token', 'IP', 'Method', 'URL', 'Time'],
            array_map(function ($tokenData) {
                return [
                    $tokenData['token'],
                    $tokenData['ip'],
                    $tokenData['method'],
                    $tokenData['url'],
                    date('Y-m-d H:i:s', $tokenData['time']),
                ];
            }, $this->tokens)
        );
    }

    /**
     * Displays details of a specific token.
     */
    private function displayTokenDetails(string $token, SymfonyStyle $io): void
    {
        // Load the profile associated with the token
        $profile = $this->profiler->loadProfile($token);

        if (!$profile) {
            $io->error(sprintf('No profile found for token: %s', $token));
            return;
        }

        // Retrieve the request details from the profiler
        /** @var \Symfony\Component\HttpKernel\DataCollector\RequestDataCollector $request */
        $request = $profile->getCollector('request');

        // Get the full URL (scheme + host + path)
        $scheme = $request->getRequestServer()->all()['REQUEST_SCHEME'] ?? 'http'; // Get the scheme (http or https)
        $host = $request->getRequestServer()->all()['HTTP_HOST']; // Get the host (domain or IP)
        $path = $request->getPathInfo(); // Get the request path
        $url = sprintf('%s://%s%s', $scheme, $host, $path); // Construct the full URL

        $method = $request->getMethod(); // Get the HTTP method
        $headers = $request->getRequestHeaders()->all(); // Get the request headers as an array
        $content = $request->getContent(); // Get the request body content

        // Display the request details with green text for labels
        $io->section('Request Details');
        $io->text(sprintf('<fg=green>URL</>: %s', $url));
        $io->text(sprintf('<fg=green>Method</>: %s', $method));

        // Display headers with yellow text for the names
        $io->text('<fg=green>Headers</>:');
        foreach ($headers as $header => $value) {
            $io->text(sprintf('- <fg=yellow>%s</>: %s', $header, is_array($value) ? implode(', ', $value) : $value));
        }

        // Display request body
        $io->section('Request Body');
        $io->text(sprintf('<fg=green>Content</>: %s', $content ?: 'No content.'));

        // Generate curl command from the request details
        $curlCommand = $this->generateCurlCommand($method, $url, $headers, $content);

        // Display the generated curl command
        $io->section('Generated curl Command');
        $io->text($curlCommand);
    }

    /**
     * Generates a curl command based on the request details.
     */
    private function generateCurlCommand(string $method, string $url, array $headers, ?string $content): string
    {
        // Start with the HTTP method and URL
        $curlCommand = sprintf("curl -X %s '%s'", strtoupper($method), $url);

        // Add headers to the curl command
        foreach ($headers as $header => $value) {
            $headerValue = is_array($value) ? implode(', ', $value) : $value;
            $curlCommand .= sprintf(" -H '%s: %s'", $header, $headerValue);
        }

        // Add the request body if it's not a GET request
        if ($method !== 'GET' && $content) {
            $curlCommand .= sprintf(" --data '%s'", addslashes($content));
        }

        return $curlCommand;
    }

    /**
     * Exports curl commands for all tokens.
     */
    private function exportAllTokens(SymfonyStyle $io): void
    {
        foreach ($this->tokens as $tokenData) {
            $this->exportTokenCurl($tokenData['token'], $io);
        }
        $io->success('All tokens have been exported.');
    }

    /**
     * Exports the curl command to a file using the format curl_YmdHis-token.txt.
     */
    private function exportTokenCurl(string $token, SymfonyStyle $io): void
    {
        // Load the profile associated with the token
        $profile = $this->profiler->loadProfile($token);

        if (!$profile) {
            $io->error(sprintf('No profile found for token: %s', $token));
            return;
        }

        // Retrieve the request details
        /** @var \Symfony\Component\HttpKernel\DataCollector\RequestDataCollector $request */
        $request = $profile->getCollector('request');

        // Generate curl command
        $curlCommand = $this->generateCurlCommand(
            $request->getMethod(),
            sprintf('%s://%s%s', $request->getRequestServer()->get('REQUEST_SCHEME', 'http'), $request->getRequestServer()->get('HTTP_HOST'), $request->getPathInfo()),
            $request->getRequestHeaders()->all(),
            $request->getContent()
        );

        // Get the request timestamp and format it as YmdHis
        $timestamp = $profile->getTime();  // Timestamp in milliseconds
        $formattedTimestamp = date('YmdHis', $timestamp);

        // Generate the filename using the timestamp and token
        $filename = sprintf('curl_%s-%s.txt', $formattedTimestamp, $token);

        // Write the curl command to the file
        file_put_contents($filename, $curlCommand);

        // Confirm export
        $io->success(sprintf('Curl command exported to file: %s', $filename));
    }

    /**
     * Runs the interactive menu for user input and handles CTRL+C to quit.
     */
    private function runInteractiveMenu(SymfonyStyle $io): void
    {
        // Set up the signal handler for CTRL+C (SIGINT)
        pcntl_signal(SIGINT, function () use ($io) {
            $io->success('Exiting program (CTRL+C).');
            exit(0); // Exit the program with a success code
        });

        // Display the menu at the bottom of the terminal
        $this->displayMenu($io);

        // Continuously read user input
        while (true) {
            // Check for pending signals and dispatch them
            pcntl_signal_dispatch();

            // Capturing a single character input
            $input = $this->getSingleCharacter();

            if ($input === 'm') { // If the user presses 'm', redisplay the token list
                $this->clearScreen();
                $this->displayTokens($io);
                $this->displayMenu($io);
            }

            if ($input === 'q') { // If the user presses 'q', quit the program
                $io->success('Exiting program.');
                break;
            }

            if ($input === 'd') { // If the user presses 'd', ask for the token and show its details
                $tokenToShow = $io->ask('Please enter the token code you want to display');
                $this->currentToken = $tokenToShow;
                $this->displayTokenDetails($tokenToShow, $io);
                $this->displayMenu($io);
            }

            if ($input === 'e' && $this->currentToken) { // If the user presses 'e', export the curl command for the current token
                $this->exportTokenCurl($this->currentToken, $io);
            }

            // Check for pending signals after each loop iteration
            pcntl_signal_dispatch();
        }
    }

    /**
     * Displays the interactive menu options.
     */
    private function displayMenu(SymfonyStyle $io): void
    {
        $io->text('M (Menu): Press "m" to redisplay the token list.');
        $io->text('D (Detail): Press "d" to display details of a token.');
        if ($this->currentToken) {
            $io->text('E (Export): Press "e" to export ' . $this->currentToken . ' as a curl command.');
        }
        $io->text('Q (Quit): Press "q" to exit.');
    }

    /**
     * Captures a single character input without waiting for Enter.
     */
    private function getSingleCharacter(): ?string
    {
        // Disable terminal input echo
        system('stty cbreak -echo');

        // Capture a single character
        $char = fgetc(STDIN);

        // Restore terminal settings
        system('stty -cbreak echo');

        return $char;
    }

    /**
     * Clears the console screen.
     */
    private function clearScreen(): void
    {
        // ANSI escape sequence to clear the console screen
        echo "\033c";
    }
}
