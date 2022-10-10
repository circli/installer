<?php declare(strict_types=1);

namespace Circli\Installer;

class PhpArrayFile implements \ArrayAccess
{
    /**
     * Full path to the php file
     */
    private string $filePath;

    /**
     * Array containing all lines of the current php file
     *
     * @var array<int, string>
     */
    private array $fileContent = [];

    /**
     * Constructor
     *
     * @param string $filePath The path to the php file
     * @param bool $autoCreate
     */
    public function __construct(string $filePath, bool $autoCreate = true)
    {
        if ($autoCreate && !file_exists($filePath)) {
            file_put_contents($filePath, '<?php return [];');
        }

        $this->setFile($filePath);
    }

    public function setFile(string $filePath): void
    {
        $this->fileContent = [];
        $this->filePath = $filePath;
        $this->checkPermissions();
        $this->parse();
    }

    public function getFile(): string
    {
        return $this->filePath;
    }

    /**
     * Permission check
     *
     * @throws \RuntimeException
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
        $this->fileContent = include $this->getFile();

        return $this;
    }

    public function save(): self
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
        if ($offset === null) {
            if (!in_array($value, $this->fileContent, true)) {
                $this->fileContent[] = $value;
            }
        }
        else {
            $this->fileContent[$offset] = $value;
        }
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->fileContent[$offset]);
    }
}
