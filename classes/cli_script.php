<?php
namespace local_lsucli;

defined('MOODLE_INTERNAL') || die();

if (!function_exists('array_find_key')) {
    #PHP version is less than 8.4
    /**
     * @template TKey of int|string
     * @template TValue
     * @param array<TKey, TValue> $array
     * @param (callable(TValue $value, TKey $key): bool)|(callable(TValue $value): bool) $callback
     * @return TKey|null
     * @since 8.4
     */
    function array_find_key(array $array, callable $callback): mixed {
        foreach ($array as $k => $v) {
            if ($callback($v, $k)) {
                return $k;
            }
        }
        return null;
    }
}

class CLIScript {
    public string $file_path;
    public string $file_name;
    public string $help_text;
    /** @var CLIOption[] $options */
    public array $options;
    public string $example_text;

    public function __construct($file_name) {
        global $CFG;
        $this->file_path = "$CFG->dirroot/admin/cli/$file_name";
        $file_name = substr($file_name, 0, -4);
        $this->file_name = ltrim(strtolower(preg_replace('/([A-Z])/', '_$1', $file_name)), '_');
        $this->grep_help_text();
        $this->parse_help_text();
    }

    function grep_help_text() {
        $full_text = file_get_contents($this->file_path);
        $lines = explode(PHP_EOL, $full_text);
        $start_idx = array_find_key($lines, function($v, $k) {
            return preg_match('/s*(\$help|\$usage|\$options\[\'help\'\]).*/', $v);
        });
        if ($start_idx !== null) {
            $lines = array_slice($lines, $start_idx + 0);
        }
        $end_idx = array_find_key($lines, function($v, $k) {
            return preg_match('/^\s*(EOT|EOL|EOF|");\s*/', $v);
        });
        if ($end_idx !== null) {
            $lines = array_slice($lines, 0, $end_idx);
        }
        $this->help_text = implode(PHP_EOL, $lines);
        $this->help_text = preg_replace(
            '/.*\$options.*/',
            '',
            $this->help_text,
        ) ?? '';
        $this->help_text = preg_replace(
            '/.*\/\/.*/',
            '',
            $this->help_text,
        ) ?? '';
        $this->help_text = preg_replace(
            '/\s*(\$help|\$usage|echo)\s*\=?\s*(<<<)?(EOT|EOL|EOF|echo)?"?\s*/',
            '',
            $this->help_text
        ) ?? '';
    }

    /**
     * Scans the script file for its information.
     */
    function parse_help_text() {
        $lines = explode(PHP_EOL, $this->help_text);
        $option_lines = [];
        $example_lines = [];
        $stage = 0;
        foreach ($lines as $line) {
            if ($stage == 0) {
                if (strpos($line, 'Options:') !== false) {
                    $stage = 1;
                    continue;
                }
            } else if ($stage == 1) {
                if (trim($line) === '' && count($option_lines) > 0) {
                    $stage = 2;
                    continue;
                }
                $option_lines[] = $line;
            } else if ($stage == 2) {
                if (preg_match('/\"\;?/', $line) || $line == 'EOT;') {
                    break;
                }
                $example_lines[] = $line;
            }
        }
        // exec("php $this->file_path -- -h", $output);
        // $this->help_text = implode(PHP_EOL, $output);
        // $this->help_text = preg_replace('/^Warning\:.*$', '', $this->help_text) ?? '';
        $this->options = CLIOption::parse_lines($option_lines);
        $this->special_cases();
        $this->example_text = implode(PHP_EOL, $example_lines);
    }

    /**
     * Scans the admin/cli folder for cli scripts and parses them for information.
     * @return CLIScript[]
     */
    public static function gen_scripts(): array {
        global $CFG;
        $results = [];
        $scripts = array_diff(scandir($CFG->dirroot . '/admin/cli'), array('..', '.'));
        foreach ($scripts as $script) {
            $new_script = new CLIScript($script);
            $results[$new_script->file_name] = $new_script;
        }
        return $results;
    }

    /**
     * @return CLIOption[]
     */
    public function get_options(): array {
        return $this->options;
    }

    /** @var array<array<array>> An array of corrected values for poor documentation in the script files. Structure is [file_name][longname][property] = <NEW_VALUE> */
    const SPECIAL_CASE_PROPS = [
        'adhoc_task' => [
            'classname' => [
                'type' => OptionType::STRING,
            ],
            'id' => [
                'type' => OptionType::NUMBER,
            ],
        ],
        'build_theme_css' => [
            'themes' => [
                'type' => OptionType::STRING,
            ]
        ],
        'checks' => [
            'filter' => [
                'type' => OptionType::STRING,
            ],
            'type' => [
                'type' => OptionType::STRING,
            ],
        ],
        'delete_course' => [
            'courseid' => [
                'type' => OptionType::STRING,
                'required' => true,
            ],
        ],
        'fix_course_sequence' => [
            'courses' => [
                'type' => OptionType::STRING,
                'required' => true,
            ],
        ],
        'generate_key' => [
            'method' => [
                'type' => OptionType::STRING,
            ],
        ],
        'purge_caches' => [
            'courses' => [
                'type' => OptionType::STRING,
            ],
        ],
    ];
    private function special_cases() {
        $params = self::SPECIAL_CASE_PROPS[$this->file_name] ?? [];
        if (empty($params)) {
            return;
        }
        foreach ($this->options as $option) {
            if (!key_exists($option->longname, $params)) {
                continue;
            }
            foreach ($params[$option->longname] as $key => $value) {
                $option->{$key} = $value;
            }
        }
    }
}