<?php

require getcwd().'/vendor/autoload.php';

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;

// For list run:
// php vendor/sushan-jobins/shared-scripts/scripts/copy-missing-env.php --dry
//
// For copying env example values to env run:
// php vendor/sushan-jobins/shared-scripts/scripts/copy-missing-env.php
//
// For status:
// php vendor/sushan-jobins/shared-scripts/scripts/copy-missing-env.php --status=all
//
// For status information:
// php vendor/sushan-jobins/shared-scripts/scripts/copy-missing-env.php --info-status

$projectRoot = getcwd();

$envFile     = $projectRoot.'/.env';
$exampleFile = $projectRoot.'/.env.example';

// Maximum character length threshold
$maxLen = 40;

if (!file_exists($exampleFile)) {
    echo "Error: .env.example file not found.\n";
    exit(1);
}

if (!file_exists($envFile)) {
    echo "Error: .env file not found.\n";
    exit(1);
}

$statusFilter = null;

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--status=')) {
        $statusFilter = substr($argument, strlen('--status='));

        break;
    }
}

$allowedStatuses = [
    'all',
    'added',
    'changed',
    'not_changed_on_env',
    'only_on_env',
    'same',
];

if ($statusFilter !== null && !in_array($statusFilter, $allowedStatuses, true)) {
    echo "Invalid status: {$statusFilter}\n";
    echo 'Allowed values: '.implode(', ', $allowedStatuses)."\n";

    exit(1);
}

function truncateText(string $text, int $limit = 50): string
{
    if (mb_strlen($text) > $limit) {
        return mb_strimwidth($text, 0, $limit, '...');
    }

    return $text;
}

function parseEnvContent(string $content): array
{
    $data = [];

    foreach (explode("\n", $content) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);

            $data[trim($key)] = trim($value);
        }
    }

    return $data;
}

/**
 * Get current .env values.
 */
function getCurrentEnvMap(string $envFile): array
{
    $envLines = file(
        $envFile,
        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
    );

    $currentEnvMap = [];

    foreach ($envLines as $line) {
        $trimmedLine = trim($line);

        if (
            !str_starts_with($trimmedLine, '#')
            && strpos($line, '=') !== false
        ) {
            [$key, $value] = explode('=', $line, 2);

            $currentEnvMap[trim($key)] = trim($value);
        }
    }

    return $currentEnvMap;
}

/**
 * Render a simple CLI table.
 */
function renderCliTable(array $headers, array $rows): void
{
    // Fixed width for each column.
    $columnWidths = [
        25,
        40,
    ];

    $separator = '+';

    foreach ($columnWidths as $width) {
        $separator .= str_repeat('-', $width + 2) . '+';
    }

    // Top border
    echo $separator . PHP_EOL;

    // Header
    echo '|';

    foreach ($headers as $index => $header) {
        $header = truncateText(
            (string) $header,
            $columnWidths[$index]
        );

        echo ' '
            . str_pad(
                $header,
                $columnWidths[$index],
                ' '
            )
            . ' |';
    }

    echo PHP_EOL;

    // Header separator
    echo $separator . PHP_EOL;

    // Rows
    foreach ($rows as $row) {
        echo '|';

        foreach ($row as $index => $value) {
            $value = truncateText(
                (string) $value,
                $columnWidths[$index]
            );

            echo ' '
                . str_pad(
                    $value,
                    $columnWidths[$index],
                    ' '
                )
                . ' |';
        }

        echo PHP_EOL;
    }

    // Bottom border
    echo $separator . PHP_EOL;
}

/**
 * Check for dry parameter robustly.
 */
function isDryRun(array $argv): bool
{
    if (in_array('--dry', $argv, true) || in_array('dry', $argv, true)) {
        return true;
    }

    if (function_exists('posix_getppid')) {
        $ppid = posix_getppid();

        if ($ppid) {
            $parentCmd = shell_exec(
                "ps -p {$ppid} -o command="
            );

            if (
                $parentCmd
                && (
                    strpos($parentCmd, '--dry') !== false
                    || preg_match('/\b(dry)\b/', $parentCmd)
                )
            ) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Display missing environment variables.
 */
function displayDryRun(
    string $exampleFile,
    array $currentEnvMap,
    int $maxLen
): void {
    $exampleKeys  = [];
    $exampleLines = file(
        $exampleFile,
        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
    );

    foreach ($exampleLines as $line) {
        $trimmedLine = trim($line);

        if (
            !str_starts_with($trimmedLine, '#')
            && strpos($line, '=') !== false
        ) {
            [$key, $value] = explode('=', $line, 2);

            $exampleKeys[trim($key)] = trim($value);
        }
    }

    $missingKeys = array_diff_key(
        $exampleKeys,
        $currentEnvMap
    );

    $missingRows = [];

    if (!empty($missingKeys)) {
        echo "The following keys are missing from your .env file:\n\n";

        foreach ($missingKeys as $key => $value) {
            $missingRows[] = [
                truncateText($key, $maxLen),
                truncateText($value, $maxLen),
            ];
        }

        // $output = new ConsoleOutput();

        // $table = new Table($output);

        // $table
        //     ->setHeaders([
        //         '.env.example',
        //         'value in .env.example',
        //     ])
        //     ->setRows($missingRows);

        // $table->render();

        renderCliTable(
            [
                '.env.example',
                'value in .env.example',
            ],
            $missingRows
        );

        echo "\n";
    } else {
        echo "Clean match! Your .env file includes all keys currently defined in your .env.example.\n";
    }
}

/**
 * Display status information.
 */
function displayStatusInfo(): void
{
    $statuses = [
        'added' => [
            'color'       => "\033[32m",
            'description' => 'Key did not exist in the previous .env but exists in the current .env.',
        ],
        'changed' => [
            'color'       => "\033[33m",
            'description' => 'Key existed before, but its value has changed in the current .env.',
        ],
        'not_changed_on_env' => [
            'color'       => "\033[31m",
            'description' => '.env value has not changed, but it differs from .env.example.',
        ],
        'same' => [
            'color'       => "\033[90m",
            'description' => 'Current .env value is the same as the value in .env.example.',
        ],
        'only_on_env' => [
            'color'       => "\033[36m",
            'description' => 'No change or update was detected for this environment variable.',
        ],
    ];

    $reset = "\033[0m";

    echo "\n\033[1;36mEnvironment Variable Status Information{$reset}\n\n";

    foreach (
        $statuses as $status => [
            'color' => $color,
            'description' => $description,
        ]
    ) {
        echo "{$color}".strtoupper($status)."{$reset}\n";
        echo "  -> {$description}\n\n";
    }
}

/**
 * Build the synchronized .env content.
 */
function buildNewEnvContent(
    string $exampleFile,
    array $currentEnvMap
): array {
    $exampleLines           = file($exampleFile, FILE_IGNORE_NEW_LINES);
    $newEnvLines            = [];
    $matchedKeysFromExample = [];

    foreach ($exampleLines as $line) {
        $trimmedLine = trim($line);

        // Keep comments and empty lines exactly where they are.
        if (
            $trimmedLine === ''
            || str_starts_with($trimmedLine, '#')
        ) {
            $newEnvLines[] = $line;

            continue;
        }

        // Process actual Key=Value pairs.
        if (strpos($line, '=') !== false) {
            [$key, $exampleValue] = explode('=', $line, 2);

            $key          = trim($key);
            $exampleValue = trim($exampleValue);

            $matchedKeysFromExample[$key] = true;

            if (array_key_exists($key, $currentEnvMap)) {
                $currentValue = trim($currentEnvMap[$key]);

                // If .env contains:
                //
                // KEY=
                // KEY=""
                // KEY=''
                //
                // use the value from .env.example.
                if (
                    $currentValue === ''
                    || $currentValue === '""'
                    || $currentValue === "''"
                ) {
                    $finalValue = $exampleValue;
                } else {
                    $finalValue = $currentValue;
                }
            } else {
                // Use the value from .env.example.
                $finalValue = $exampleValue;
            }

            $newEnvLines[] = "{$key}={$finalValue}";
        }
    }

    // Find extra keys that exist only in .env.
    $extraKeys = array_diff_key(
        $currentEnvMap,
        $matchedKeysFromExample
    );

    if (!empty($extraKeys)) {
        $newEnvLines[] = '';
        $newEnvLines[] = '# --- Custom keys unique to your local .env configuration ---';

        foreach ($extraKeys as $extraKey => $extraValue) {
            $newEnvLines[] = "{$extraKey}={$extraValue}";
        }
    }

    return $newEnvLines;
}

/**
 * Generate status rows.
 */
function generateStatusRows(
    array $oldValues,
    array $newValues,
    array $exampleValues,
    ?string $statusFilter
): array {
    $allKeys = array_unique(
        array_merge(
            array_keys($oldValues),
            array_keys($newValues),
            array_keys($exampleValues)
        )
    );

    $rows = [];

    $statusOrder = [
        'added'              => 1,
        'changed'            => 2,
        'not_changed_on_env' => 3,
        'only_on_env'          => 4,
        'same'               => 5,
    ];

    foreach ($allKeys as $key) {
        $prev    = $oldValues[$key] ?? null;
        $curr    = $newValues[$key] ?? null;
        $example = $exampleValues[$key] ?? null;

        if ($prev === null && $curr !== null) {
            $status = 'added';
        } elseif (
            $prev !== null
            && $curr !== null
            && $prev !== $curr
        ) {
            $status = 'changed';
        } elseif (
            $prev !== null
            && $curr !== null
            && $prev === $curr
            && $example !== null
            && $curr !== $example
        ) {
            $status = 'not_changed_on_env';
        } elseif (
            $curr !== null
            && $example !== null
            && $curr === $example
        ) {
            $status = 'same';
        } else {
            $status = 'only_on_env';
        }

        // When no status is supplied, don't show "same".
        if (
            $statusFilter === null
            && $status === 'same'
        ) {
            continue;
        }

        if (
            $statusFilter !== null
            && $statusFilter !== 'all'
            && $status !== $statusFilter
        ) {
            continue;
        }

        $rows[] = [
            'key'      => $key,
            'example'  => $example ?? '',
            'previous' => $prev ?? '-',
            'current'  => $curr ?? '',
            'status'   => $status,
        ];
    }

    usort(
        $rows,
        function ($a, $b) use ($statusOrder) {
            return $statusOrder[$a['status']]
                <=> $statusOrder[$b['status']];
        }
    );

    return $rows;
}

/**
 * Format rows for Symfony Console table.
 */
function formatRows(array $rows, int $maxLen): array
{
    return array_map(
        function ($row) use ($maxLen) {
            $color = match ($row['status']) {
                'added'              => 'green',
                'changed'            => 'yellow',
                'not_changed_on_env' => 'red',
                'only_on_env'          => 'cyan',
                'same'               => 'gray',
            };

            return [
                "<fg={$color}>"
                    .truncateText($row['key'], $maxLen)
                    .'</>',

                "<fg={$color}>"
                    .truncateText($row['example'], $maxLen)
                    .'</>',

                "<fg={$color}>"
                    .truncateText($row['previous'], $maxLen)
                    .'</>',

                "<fg={$color}>"
                    .truncateText($row['current'], $maxLen)
                    .'</>',

                "<fg={$color}>"
                    .$row['status']
                    .'</>',
            ];
        },
        $rows
    );
}

/**
 * Display status table.
 */
function displayStatusTable(
    array $rows
): void {
    $output = new ConsoleOutput();

    $table = new Table($output);

    $table
        ->setHeaders([
            'env',
            'value in example',
            'previous value in env',
            'current value in env',
            'status',
        ])
        ->setRows($rows);

    $table->render();
}

/**
 * Ask the user whether .env should be updated.
 */
function confirmEnvUpdate(
    string $envFile,
    string $newEnvRaw,
    string $currentEnvRaw
): void {
    if ($currentEnvRaw === $newEnvRaw) {
        echo "Your .env file is already perfectly sorted and up to date with .env.example!\n";

        return;
    }

    echo "\n";
    echo "\033[33m⚠ Changes from .env.example are available for your .env file\033[0m\n";
    echo "\033[36mApply these changes to .env? \033[33m(yes/no) [no]: \033[0m";

    $answer = trim(fgets(STDIN));

    if (in_array(strtolower($answer), ['yes', 'y'], true)) {
        file_put_contents(
            $envFile,
            $newEnvRaw
        );

        echo "\033[32mSuccess: Re-synchronized your .env file layout! Structure updated, custom values preserved, and blank values left clean.\033[0m\n";
    } else {
        echo "\033[33mSkipped: .env file was not updated.\033[0m\n";
    }
}

/*
|--------------------------------------------------------------------------
| Main
|--------------------------------------------------------------------------
*/

$isDryRun = isDryRun($argv);

if ($isDryRun) {
    $currentEnvMap = getCurrentEnvMap($envFile);

    displayDryRun(
        $exampleFile,
        $currentEnvMap,
        $maxLen
    );

    exit(0);
}

if (in_array('--info-status', $argv, true)) {
    displayStatusInfo();

    exit(0);
}

// Read current .env.
$currentEnvRaw = file_get_contents($envFile);

// Parse current .env.
$oldValues = parseEnvContent($currentEnvRaw);

// Build new .env content.
$newEnvLines = buildNewEnvContent(
    $exampleFile,
    getCurrentEnvMap($envFile)
);

// Generate new raw .env content.
$newEnvRaw = implode("\n", $newEnvLines)."\n";

// Parse generated .env.
$newValues = parseEnvContent($newEnvRaw);

// Parse .env.example.
$exampleValues = parseEnvContent(
    file_get_contents($exampleFile)
);

// Generate status rows.
$rows = generateStatusRows(
    $oldValues,
    $newValues,
    $exampleValues,
    $statusFilter
);

// Format rows.
$formattedRows = formatRows(
    $rows,
    $maxLen
);

// Display table.
displayStatusTable($formattedRows);

// Ask whether changes should be applied.
confirmEnvUpdate(
    $envFile,
    $newEnvRaw,
    $currentEnvRaw
);

exit(0);