<?php

namespace Circli\Installer;

class PhpFile implements \ArrayAccess
{
    /**
     * Full path to the php file
     *
     * @var string
     */
    private $filePath;

    /**
     * Array containing all lines of the current php file
     *
     * @var array
     */
    private $fileContent = [];

    /**
     * Constructor
     *
     * @param string $filePath The path to the php file
     * @param bool $autoCreate
     */
    public function __construct($filePath, bool $autoCreate = true)
    {
        if ($autoCreate && !file_exists($filePath)) {
            file_put_contents($filePath, "<?php\n");
        }

        $this->setFile($filePath);
    }

    /**
     * Sets the file
     *
     * @param string $filePath The path to the php file
     */
    public function setFile($filePath): void
    {
        $this->fileContent = array();
        $this->filePath = $filePath;
        $this->checkPermissions();
        $this->parse();
    }

    /**
     * Gets the file path
     *
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
     *
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

    /**
     * Read the array from file
     *
     * @return self
     */
    protected function parse(): self
    {
        $this->fileContent = file($this->getFile(), FILE_IGNORE_NEW_LINES);

        return $this;
    }

    /**
     * Save to the php file
     *
     * @return self
     */
    public function save()
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
    public function offsetExists($offset)
    {
        return isset($this->fileContent[$offset]);
    }

    /**
     * @inheritdoc
     */
    public function offsetGet($offset)
    {
        return $this->fileContent[$offset];
    }

    /**
     * @inheritdoc
     */
    public function offsetSet($offset, $value)
    {
        $this->fileContent[] = $value;
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset)
    {
        unset($this->fileContent[$offset]);
    }

    public function addInclude(string $file)
    {
        $insertIndex = 1;
        $includeCount = 1;
        foreach ($this->fileContent as $index => $line) {
            if (strpos($line, $file) !== false) {
                return;
            }
            if (strpos($line, 'include(')) {
                $includeCount++;
                $insertIndex = $index + 1;
                continue;
            }
            if (strpos($line, 'return ') === 0) {
                $insertIndex = $index;
                break;
            }
        }

        $statement = '$include' . $includeCount . " = include('$file');";

        array_splice($this->fileContent, $insertIndex, 0, $statement);
    }

    public function getIncludes(): array
    {
        $includes = [];
        foreach ($this->fileContent as $index => $line) {
            if (strpos($line, 'include(')) {
                preg_match('/(\$\w+)(\s+)?=(\s+)?include[\( ][\'\"]([\_\-\w.]+)[\'\"]/', $line, $match);
                $includes[$match[1]] = $match[4];
                continue;
            }
            if (strpos($line, 'return ') === 0) {
                break;
            }
        }

        return $includes;
    }

    public function addStatment($stmt)
    {
        $this->fileContent[] = $stmt;
    }

    public function replaceConfigMerge()
    {
        $mergeIndex = -1;
        $returnIndex = -1;

        foreach ($this->fileContent as $index => $line) {
            if (strpos($line, '$mergeConfig') === 0) {
                $mergeIndex = $index;
                continue;
            }
            if (strpos($line, 'return ') === 0) {
                $returnIndex = $index;
                break;
            }
        }

        $includes = $this->getIncludes();

        $statement = '$mergeConfig = array_merge('.implode(', ', array_keys($includes)).');';

        $index = -1;
        $length = 0;
        if ($mergeIndex !== -1) {
            $index = $mergeIndex;
            $length = 1;
        } elseif ($returnIndex !== -1) {
            $index = $returnIndex;
        }

        if ($index === -1) {
            return;
        }

        array_splice($this->fileContent, $index, $length, $statement);
    }

    public function addReturnStatment(string $variable)
    {
        foreach ($this->fileContent as $index => $line) {
            if (strpos($line, 'return ') === 0) {
                return;
            }
        }
        $this->fileContent[] = 'return $'.$variable;
    }
}
