<?php


$projectRoot = getcwd();

$envFile     = $projectRoot.'/.env';
$exampleFile = $projectRoot.'/.env.example';

// Maximum character length threshold
$maxLen = 40;

if (!file_exists($exampleFile)) {
    echo "\033[31mError: .env.example file not found.\033[0m\n";
    exit(1);
}

if (!file_exists($envFile)) {
    echo "\033[31mError: .env file not found.\033[0m\n";
    exit(1);
}

function truncateText(string $text, int $limit = 40): string
{
    if (strlen($text) <= $limit) {
        return $text;
    }

    if ($limit <= 3) {
        return substr($text, 0, $limit);
    }

    return substr($text, 0, $limit - 3).'...';
}

function parseEnvContent(string $content): array
{
    $data = [];

    foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);

        $key = trim($key);

        if ($key === '') {
            continue;
        }

        $data[$key] = trim($value);
    }

    return $data;
}

/**
 * Read environment file into a key/value map.
 *
 * @return array<string, string>
 */
function getCurrentEnvMap(string $envFile): array
{
    $content = file_get_contents($envFile);

    if ($content === false) {
        return [];
    }

    return parseEnvContent($content);
}

/**
 * Check whether dry-run mode was requested.
 */
function isDryRun(array $argv): bool
{
    return in_array('--dry', $argv, true)
        || in_array('dry', $argv, true);
}

/**
 * Get the requested status filter.
 */
function getStatusFilter(array $argv): ?string
{
    foreach ($argv as $argument) {
        if (str_starts_with($argument, '--status=')) {
            return substr($argument, strlen('--status='));
        }
    }

    return null;
}

/**
 * Remove ANSI escape sequences from text.
 */
function stripAnsi(string $text): string
{
    return preg_replace('/\033\[[0-9;]*m/', '', $text) ?? $text;
}

/**
 * Display a CLI table.
 *
 */
function displayCliTable(
    array $headers,
    array $rows
): void {
    if (empty($rows)) {
        echo "No environment variables found for the selected status.\n";

        return;
    }

    $columnWidths = [];

    foreach ($headers as $index => $header) {
        $columnWidths[$index] = strlen((string) $header);
    }

    foreach ($rows as $row) {
        foreach ($row as $index => $value) {
            $plainValue = stripAnsi((string) $value);

            $columnWidths[$index] = max(
                $columnWidths[$index] ?? 0,
                strlen($plainValue)
            );
        }
    }

    $headerColor = "\033[1;36m";
    $reset = "\033[0m";

    /*
     * Build separator.
     */
    $separator = '+';

    foreach ($columnWidths as $width) {
        $separator .= str_repeat('-', $width + 2).'+';
    }

    /*
     * Header.
     */
    echo $separator."\n";
    echo '|';

    foreach ($headers as $index => $header) {
        echo ' '
            .$headerColor
            .str_pad(
                (string) $header,
                $columnWidths[$index]
            )
            .$reset
            .' |';
    }

    echo "\n";
    echo $separator."\n";

    /*
     * Rows.
     */
    foreach ($rows as $row) {
        echo '|';

        foreach ($row as $index => $value) {
            $value = (string) $value;

            $plainValue = stripAnsi($value);

            $padding = $columnWidths[$index] - strlen($plainValue);

            echo ' '
                .$value
                .str_repeat(' ', max(0, $padding))
                .' |';
        }

        echo "\n";
    }

    echo $separator."\n";
}

/**
 * Get ANSI color for a status.
 */
function getStatusColor(string $status): string
{
    return match ($status) {
        'added'              => "\033[32m", // Green
        'changed'            => "\033[33m", // Yellow
        'not_changed_on_env' => "\033[31m", // Red
        'only_on_env'        => "\033[36m", // Cyan
        'same'               => "\033[90m", // Gray
        default              => "\033[0m",
    };
}

/**
 * Format rows with status colors.
 */
function formatRows(
    array $rows,
    int $maxLen
): array {
    return array_map(
        function (array $row) use ($maxLen): array {
            $color = getStatusColor($row['status']);
            $reset = "\033[0m";

            return [
                $color
                    .truncateText($row['key'], $maxLen)
                    .$reset,

                $color
                    .truncateText($row['example'], $maxLen)
                    .$reset,

                $color
                    .truncateText($row['previous'], $maxLen)
                    .$reset,

                $color
                    .truncateText($row['current'], $maxLen)
                    .$reset,

                $color
                    .$row['status']
                    .$reset,
            ];
        },
        $rows
    );
}

/**
 * Display missing environment variables.
 */
function displayDryRun(
    string $exampleFile,
    array $currentEnvMap,
    int $maxLen
): void {
    $exampleContent = file_get_contents($exampleFile);

    if ($exampleContent === false) {
        echo "\033[31mError: Unable to read .env.example.\033[0m\n";
        exit(1);
    }

    $exampleValues = parseEnvContent($exampleContent);

    $missingKeys = array_diff_key(
        $exampleValues,
        $currentEnvMap
    );

    if (empty($missingKeys)) {
        echo "\033[32mClean match!\033[0m ";
        echo "Your .env file includes all keys currently defined in your .env.example.\n";

        return;
    }

    echo "\n";
    echo "\033[1;33mThe following keys are missing from your .env file:\033[0m\n\n";

    $rows = [];

    foreach ($missingKeys as $key => $value) {
        $rows[] = [
            truncateText($key, $maxLen),
            truncateText($value, $maxLen),
        ];
    }

    displayCliTable(
        [
            '.env.example',
            'value in .env.example',
        ],
        $rows
    );

    echo "\n";
}

/**
 * Display status descriptions.
 */
function displayStatusInfo(): void
{
    $statuses = [
        'added' => [
            'color' => "\033[32m",
            'description' =>
                'Key did not exist in the previous .env but exists in the current .env.',
        ],

        'changed' => [
            'color' => "\033[33m",
            'description' =>
                'Key existed before, but its value has changed in the current .env.',
        ],

        'not_changed_on_env' => [
            'color' => "\033[31m",
            'description' =>
                '.env value has not changed, but it differs from .env.example.',
        ],

        'only_on_env' => [
            'color' => "\033[36m",
            'description' =>
                'Environment variable exists in .env but is not defined in .env.example.',
        ],

        'same' => [
            'color' => "\033[90m",
            'description' =>
                'Current .env value is the same as the value in .env.example.',
        ],
    ];

    $reset = "\033[0m";

    echo "\n";
    echo "\033[1;36mEnvironment Variable Status Information{$reset}\n";
    echo "\n";

    foreach ($statuses as $status => $info) {
        echo $info['color']
            .strtoupper($status)
            .$reset
            ."\n";

        echo "  -> ".$info['description']."\n\n";
    }
}


/**
 * Build synchronized .env content.
 *
 * Values already present in .env are preserved.
 *
 * Missing values are copied from .env.example.
 *
 * Empty values such as:
 *
 * KEY=
 * KEY=""
 * KEY=''
 *
 * are replaced with the .env.example value.
 *
 * Custom keys existing only in .env are preserved at the bottom.
 */
function buildNewEnvContent(
    string $exampleFile,
    array $currentEnvMap
): array {
    $exampleContent = file_get_contents($exampleFile);

    if ($exampleContent === false) {
        return [];
    }

    $exampleLines = preg_split(
        '/\r\n|\r|\n/',
        $exampleContent
    );

    $newEnvLines = [];

    $matchedKeysFromExample = [];

    foreach ($exampleLines as $line) {
        $trimmedLine = trim($line);

        /*
         * Keep comments and empty lines.
         */
        if (
            $trimmedLine === ''
            || str_starts_with($trimmedLine, '#')
        ) {
            $newEnvLines[] = $line;

            continue;
        }

        /*
         * Ignore lines without "=".
         */
        if (!str_contains($line, '=')) {
            $newEnvLines[] = $line;

            continue;
        }

        [$key, $exampleValue] = explode('=', $line, 2);

        $key = trim($key);
        $exampleValue = trim($exampleValue);

        if ($key === '') {
            $newEnvLines[] = $line;

            continue;
        }

        $matchedKeysFromExample[$key] = true;

        /*
         * Key exists in .env.
         */
        if (array_key_exists($key, $currentEnvMap)) {
            $currentValue = trim($currentEnvMap[$key]);

            /*
             * Replace empty .env values with example value.
             *
             * KEY=
             * KEY=""
             * KEY=''
             */
            if (
                $currentValue === ''
                || $currentValue === '""'
                || $currentValue === "''"
            ) {
                $finalValue = $exampleValue;
            } else {
                /*
                 * Preserve existing .env value.
                 */
                $finalValue = $currentValue;
            }
        } else {
            /*
             * Key does not exist in .env.
             *
             * Copy from .env.example.
             */
            $finalValue = $exampleValue;
        }

        $newEnvLines[] = "{$key}={$finalValue}";
    }

    /*
     * Find keys that exist only in .env.
     */
    $extraKeys = array_diff_key(
        $currentEnvMap,
        $matchedKeysFromExample
    );

    if (!empty($extraKeys)) {
        $newEnvLines[] = '';
        $newEnvLines[] =
            '# --- Custom keys unique to your local .env configuration ---';

        foreach ($extraKeys as $extraKey => $extraValue) {
            $newEnvLines[] = "{$extraKey}={$extraValue}";
        }
    }

    return $newEnvLines;
}

/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

/**
 * Generate environment variable status rows.
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
        'only_on_env'        => 4,
        'same'               => 5,
    ];

    foreach ($allKeys as $key) {
        $prev = $oldValues[$key] ?? null;
        $curr = $newValues[$key] ?? null;
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

        /*
         * When no status is provided,
         * don't show "same".
         */
        if (
            $statusFilter === null
            && $status === 'same'
        ) {
            continue;
        }

        /*
         * Apply requested status filter.
         */
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

    /*
     * Sort by status.
     */
    usort(
        $rows,
        function (array $a, array $b) use ($statusOrder): int {
            return $statusOrder[$a['status']]
                <=> $statusOrder[$b['status']];
        }
    );

    return $rows;
}

/**
 * Display status table.
 */
function displayStatusTable(array $rows): void
{
    displayCliTable(
        [
            'env',
            'value in example',
            'previous value in env',
            'current value in env',
            'status',
        ],
        $rows
    );
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
        echo "\n";
        echo "\033[32mYour .env file is already up to date!\033[0m\n";

        return;
    }

    echo "\n";
    echo "\033[1;33m⚠ Changes from .env.example are available for your .env file.\033[0m\n";
    echo "\033[36mApply these changes to .env? \033[33m(yes/no) [no]: \033[0m";

    $answer = fgets(STDIN);

    if ($answer === false) {
        echo "\n";
        echo "\033[33mSkipped: .env file was not updated.\033[0m\n";

        return;
    }

    $answer = trim($answer);

    if (in_array(strtolower($answer), ['yes', 'y'], true)) {
        $result = file_put_contents(
            $envFile,
            $newEnvRaw
        );

        if ($result === false) {
            echo "\n";
            echo "\033[31mError: Unable to update .env file.\033[0m\n";

            return;
        }

        echo "\n";
        echo "\033[32mSuccess: .env file updated successfully.\033[0m\n";
    } else {
        echo "\n";
        echo "\033[33mSkipped: .env file was not updated.\033[0m\n";
    }
}

/**
 * Main Code
 */

$isDryRun = isDryRun($argv);

$statusFilter = getStatusFilter($argv);
$allowedStatuses = [
    'all',
    'added',
    'changed',
    'not_changed_on_env',
    'only_on_env',
    'same',
];

if (
    $statusFilter !== null
    && !in_array($statusFilter, $allowedStatuses, true)
) {
    echo "\033[31mInvalid status: {$statusFilter}\033[0m\n";
    echo 'Allowed values: '
        .implode(', ', $allowedStatuses)
        ."\n";

    exit(1);
}

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

if ($currentEnvRaw === false) {
    echo "\033[31mError: Unable to read .env file.\033[0m\n";
    exit(1);
}

$exampleRaw = file_get_contents($exampleFile);

if ($exampleRaw === false) {
    echo "\033[31mError: Unable to read .env.example file.\033[0m\n";
    exit(1);
}

$oldValues = parseEnvContent($currentEnvRaw);

$exampleValues = parseEnvContent($exampleRaw);

$newEnvLines = buildNewEnvContent(
    $exampleFile,
    $oldValues
);

if (empty($newEnvLines)) {
    echo "\033[31mError: Unable to generate new .env content.\033[0m\n";
    exit(1);
}

$newEnvRaw = implode("\n", $newEnvLines)."\n";

// Parse generated .env.
$newValues = parseEnvContent($newEnvRaw);

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