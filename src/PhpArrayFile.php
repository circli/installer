<?php

namespace Circli\Installer;

class PhpArrayFile implements \ArrayAccess
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
            file_put_contents($filePath, '<?php return [];');
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
        $this->fileContent = include $this->getFile();

        return $this;
    }

    /**
     * Save to the php file
     *
     * @return self
     */
    public function save()
    {
        $output = var_export($this->fileContent, true);

        if (!file_put_contents($this->getFile(), "<?php\nreturn $output;")) {
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
        $this->fileContent[$offset] = $value;
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset)
    {
        unset($this->fileContent[$offset]);
    }
}