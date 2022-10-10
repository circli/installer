<?php declare(strict_types=1);

namespace Circli\Installer;

class PhpFile implements \ArrayAccess
{
    /**
     * Full path to the php file
     *
     * @var string
     */
    private string $filePath;

    /**
     * Array containing all lines of the current php file
     *
     * @var array<int, string>
     */
    private array $fileContent = [];

    /**
     * @param string $filePath The path to the php file
     * @param bool $autoCreate
     */
    public function __construct(string $filePath, bool $autoCreate = true)
    {
        if ($autoCreate && !file_exists($filePath)) {
            file_put_contents($filePath, "<?php\n ");
        }

        $this->setFile($filePath);
    }

    /**
     * @param string $filePath The path to the php file
     */
    public function setFile(string $filePath): void
    {
        $this->fileContent = [];
        $this->filePath = $filePath;
        $this->checkPermissions();
        $this->parse();
    }

    /**
     * @return string
     */
    public function getFile(): string
    {
        return $this->filePath;
    }

    /**
     * Permission check
     *
     * @throws \RuntimeException
     * @return self
     */
    protected function checkPermissions(): self
    {
        $file = $this->getFile();
        if (!file_exists($file)) {
            if (!is_writable(\dirname($file))) {
                throw new \RuntimeException('You don\'t have the permissions to create ' . $file . '.');
            }
            touch($file);
        } elseif (!is_writable($file)) {
            throw new \RuntimeException('You don\'t have the permissions to edit ' . $file . '.');
        }

        return $this;
    }

    protected function parse(): self
    {
        $this->fileContent = file($this->getFile(), FILE_IGNORE_NEW_LINES);

        return $this;
    }

    public function save(): self
    {
        $output = $this->fileContent;

        if (!file_put_contents($this->getFile(), implode(PHP_EOL, $output))) {
            throw new \RuntimeException('Saving to ' . $this->getFile() . ' failed.');
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->fileContent[$offset]);
    }

    /**
     * @inheritdoc
     */
    public function offsetGet(mixed $offset): ?string
    {
        return $this->fileContent[$offset];
    }

    /**
     * @inheritdoc
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->fileContent[] = $value;
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->fileContent[$offset]);
    }

    public function addInclude(string $file): void
    {
        $insertIndex = 1;
        $includeCount = 1;
        foreach ($this->fileContent as $index => $line) {
            if (str_contains($line, $file)) {
                return;
            }
            if (str_contains($line, 'include(')) {
                $includeCount++;
                $insertIndex = $index + 1;
                continue;
            }
            if (str_contains($line, 'array_merge(')) {
                $insertIndex = $index;
                break;
            }

            if (str_starts_with($line, 'return ')) {
                $insertIndex = $index;
                break;
            }
        }

        $statement = '$include' . $includeCount . " = include(__DIR__ . '/$file');";

        if (!isset($this->fileContent[$insertIndex])) {
            $this->fileContent[] = $statement;
        }
        else {
            array_splice($this->fileContent, $insertIndex, 0, $statement);
        }

    }

    /**
     * @return array<string, string>
     */
    public function getIncludes(): array
    {
        $includes = [];
        foreach ($this->fileContent as $index => $line) {
            if (strpos($line, 'include(')) {
                $rs = preg_match('/(\$\w+)(\s+)?=(\s+)?include[\( ]((\s+)?__DIR__(\s+)?.(\s+)?)?[\'\"]([\_\-\w.\/]+)[\'\"]/', $line, $match);
                if ($rs) {
                    $dir = '';
                    if ($match[4]) {
                        $dir = $match[4];
                    }

                    $includes[$match[1]] =  $dir . $match [8];
                }
                continue;
            }
            if (str_starts_with($line, 'return ')) {
                break;
            }
        }

        return $includes;
    }

    public function addStatement(string $stmt): void
    {
        $this->fileContent[] = $stmt;
    }

    public function replaceConfigMerge(): void
    {
        $mergeIndex = -1;
        $returnIndex = -1;

        foreach ($this->fileContent as $index => $line) {
            if (str_starts_with($line, '$mergeConfig ')) {
                $mergeIndex = $index;
                continue;
            }
            if (str_starts_with($line, 'return ')) {
                $returnIndex = $index;
                break;
            }
        }

        $includes = $this->getIncludes();

        if (count($includes)) {
            $statement = '$mergeConfig = array_merge((array)'.implode(', (array)', array_keys($includes)).');';
        }
        else {
            return;
        }

        $index = -1;
        $length = 0;
        if ($mergeIndex !== -1) {
            $index = $mergeIndex;
            $length = 1;
        } elseif ($returnIndex !== -1) {
            $index = $returnIndex;
        }

        if ($index === -1) {
            $this->fileContent[] = $statement;
            return;
        }

        array_splice($this->fileContent, $index, $length, $statement);
    }

    public function addReturnStatement(string $variable)
    {
        foreach ($this->fileContent as $index => $line) {
            if (str_starts_with($line, 'return ')) {
                return;
            }
        }
        $this->fileContent[] = 'return $'.$variable.';';
    }
}
