<?php
declare(strict_types=1);
namespace Hengeb\Simplates;

/**
 * @author Henrik Gebauer <code@henrik-gebauer.de>
 * @license https://opensource.org/license/mit MIT
 */

class TemplateVariable implements \Iterator, \ArrayAccess, \Countable {
    protected $iteratorPosition = 0;

    /**
     * @param string $escapeType one of 'html' or 'raw' (no escaping)
     */
    public function __construct(
        protected string $name,
        protected mixed $value,
        protected string $escapeType,
        protected Engine $engine,
    )
    {
    }

    public static function create(string $name, mixed $value, string $escapeType = 'html', Engine $engine): mixed
    {
        if (is_null($value) || $value instanceof static) {
            return $value;
        } else {
            return new static($name, $value, $escapeType, $engine);
        }
    }

    /**
     * $templateVariable->foo = bla
     * @throws \LogicException because the variable is read-only
     */
    public function __set(string $propertyName, mixed $value): never
    {
        throw new \LogicException('template variables are read-only');
    }

    /**
     * $templateVariable->foo
     */
    public function __get(string $propertyName): mixed
    {
        return match ($propertyName) {
            'raw' => $this->raw(),
            default => $this->offsetGet($propertyName),
        };
    }

    /**
     * isset($templateVariable->foo), enoty($templateVariable->foo)
     */
    public function __isset(string $propertyName): bool
    {
        return isset($this->value->$propertyName);
    }

    /**
     * $templateVariable->foo(bar)
     */
    public function __call(string $name, array $arguments): mixed
    {
        // special handling for DateTime objects: set timezone
        if (($this->value instanceof \DateTimeImmutable || $this->value instanceof \DateTime) && $name === 'format') {
            $timezone = null;
            if (isset($arguments[1])) {
                $timezone = new \DateTimeZone($arguments[1]);
            } elseif ($t = $this->engine->get('_timeZone')) {
                $timezone = $t;
            }
            if ($timezone) {
                $value = clone $this->value;
                $value = $value->setTimeZone($timezone);
            } else {
                $value = $this->value;
            }
            return static::create($name, $value->format($arguments[0]), $this->escapeType, $this->engine);
        }

        return static::create($name, $this->value->$name(...$arguments), $this->escapeType, $this->engine);
    }

    /**
     * $templateVariable(bar)
     */
    public function __invoke(...$arguments): mixed
    {
        if (!is_callable($this->value)) {
            throw new \LogicException('tried to call non-callable ' . $this->name);
        }
        return static::create($this->name, call_user_func($this->value, ...$arguments), $this->escapeType, $this->engine);
    }

    /**
     * get the name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * get the wrapped value
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * check if value is empty (or fals'ish)
     */
    public function isEmpty(): bool
    {
        return empty($this->value);
    }

    /**
     * check if value is truthful
     */
    public function isTrue(): bool
    {
        return (bool)$this->value;
    }

    /**
     * return the raw string value (not escaped)
     */
    public function raw(): string
    {
        if ($this->value instanceof \Closure) {
            return $this->value();
        } elseif (is_array($this->value)) {
            return $this->json();
        } elseif (is_object($this->value)) {
            return var_export($this->value, true);
        } else {
            return (string)$this->value;
        }
    }

    /**
     * return the json encoded value
     */
    public function json(bool $pretty = false): string
    {
        return json_encode($this->value, $pretty ? JSON_PRETTY_PRINT : 0);
    }

    /**
     * (string)$templateVariable, echo $templateVariable, <?=$templateVariable?>
     */
    public function __toString(): string
    {
        return match($this->escapeType) {
            'raw' => $this->raw(),
            'html' => $this->escape(),
            default => throw new \Exception('escape type not implemented: ' . $this->escapeType),
        };
    }

    public function escape(): string
    {
        return $this->engine->htmlEscape($this->raw());
    }

    // ArrayAccess methods
    /**
     * implements \ArrayAccess::offsetExists
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->value[$offset]);
    }

    /**
     * implements \ArrayAccess::offsetGet
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (is_array($this->value)) {
            $value = $this->value[$offset] ?? null;
        } elseif (is_object($this->value)) {
            $value = $this->value->$offset ?? null;
        } else {
            throw new \LogicException('tried to access property ' . $offset . ' of non-object and non-array ' . $this->name);
        }
        return static::create($this->name . '[' . ((string)$offset) . ']', $value, $this->escapeType, $this->engine);
    }

    /**
     * implements \ArrayAccess::offsetSet
     */
    public function offsetSet($offset, $value): void
    {
        throw new \LogicException('template variables are read-only');
    }

    /**
     * implements \ArrayAccess::offsetUnset
     */
    public function offsetUnset($offset): void
    {
        throw new \LogicException('template variables are read-only');
    }

    // Iterator methods
    /**
     * implements \Iterator::current
     */
    public function current(): mixed
    {
        $key = array_keys($this->value)[$this->iteratorPosition];
        $value = $this->value[$key];
        return static::create($this->name . '[' . $key . ']', $value, $this->escapeType, $this->engine);
    }

    /**
     * implements \Iterator::key
     */
    public function key(): mixed
    {
        return array_keys($this->value)[$this->iteratorPosition];
    }

    /**
     * implements \Iterator::next
     */
    public function next(): void
    {
        ++$this->iteratorPosition;
    }

    /**
     * implements \Iterator::rewind
     */
    public function rewind(): void
    {
        $this->iteratorPosition = 0;
    }

    /**
     * implements \Iterator::valid
     */
    public function valid(): bool
    {
        return $this->iteratorPosition < count($this->value);
    }

    /**
     * implements \Iterator::count
     */
    public function count(): int
    {
        return count($this->value);
    }

    // convenience functions
    /**
     * HTML table of the array content
     */
    public function htmlTable(): string
    {
        if (!$this->value) {
            return '';
        } elseif (!is_array($this->value) || !is_array_($this->value[0])) {
            throw new \LogicException($this->name . ' is not a two-dimensional array');
        }

        $html = "<tr>" . array_reduce(array_keys($this->value[0]), fn($res, $key) => $res . "<th>" . $this->engine->htmlEscape($key) . "</th>") . "</tr>\n";
        $html .= array_reduce($this->value,
            fn($res, $row) => $res . "<tr>" . array_reduce($row,
                fn($res2, $value) => $res2 . "<td>" . $this->engine->htmlEscape($value) . "</td>"
            ) . "</tr>\n");
        return "<table>\n$html\n</table>\n";
    }

    /**
     * HTML list of a one-dimensional array content
     * @param string $listType 'ol' or 'ul' or 'table' or '' ('' => no list tag)
     */
    public function htmlList(string $listType = 'ol'): string
    {
        if (!$this->value) {
            return '';
        } elseif (!is_array($this->value)) {
            throw new \LogicException($this->name . ' is not an array');
        }

        $openTag = $listType === 'table' ? '<tr><td>' : '<li>';
        $closeTag = $listType === 'table' ? '</td></tr>' : '</li>';
        $html = array_reduce($this->value, fn($res, $value) => $res . $openTag . $this->engine->htmlEscape($value) . $closeTag . PHP_EOL);
        return match($listType) {
            'ol', 'ul' => "<$listType>\n$html</$listType>\n",
            'table' => "<table>\n<tr><th>" . $this->engine->htmlEscape($this->name) . "</th></tr>\n$html</table>\n",
            '' => $html,
            default => throw new \InvalidArgumentException("invalid list type: $listType"),
        };
    }

    public function dump(): string
    {
        return '<pre>' . $this->__toString() . '</pre>';
    }

    public function implode(string $separator = ', '): string
    {
        return implode($separator, $this->value);
    }

    public function inputHidden(string $name = ''): string
    {
        return '<input type="hidden" name="' . ($name ?: $this->name) . '" value="' . (string)$this . '">';
    }

    public function input(...$attributes): string
    {
        $attributes['type'] ??= 'text';
        $attributes['name'] ??= $this->name;
        $attributes['value'] ??= $this->value;
        $attributes['class'] ??= 'form-control';

        if ($attributes['type'] == 'text') {
            unset($attributes['type']);
        } elseif ($attributes['type'] == 'date') {
            if (!$attributes['value']) {
                $attributes['value'] = '0000-00-00';
            } elseif ($attributes['value'] instanceof \DateTimeInterface) {
                $attributes['value'] = $attributes['value']->format('Y-m-d');
            }
        }
        $label = '';
        if (isset($attributes['label'])) {
            $attributes['placeholder'] ??= $attributes['label'];

            $attributes['id'] ??= $this->name;
            $label = sprintf('<label for="%s">%s</label>', $attributes['id'], $attributes['label']);
            unset($attributes['label']);
        }

        return $label . '<input ' . $this->htmlAttributes($attributes) . '>';
    }

    public function box(...$attributes): string
    {
        $attributes['type'] ??= 'checkbox';
        $attributes['name'] ??= $this->name;

        $attributes['label'] ??= $this->name;
        $label = $attributes['label'];
        unset($attributes['label']);

        if ($attributes['type'] == 'checkbox') {
            $attributes['checked'] ??= (bool) $this->value;
        } elseif ($attributes['type'] == 'radio') {
            $attributes['value'] ??= $this->value;
            $attributes['checked'] ??= $attributes['value'] == $this->value;
        }

        $box = '<input' . $this->htmlAttributes($attributes) . '>';
        return $label ? "<label>$box $label</label>" : $box;
    }

    public function textarea(...$attributes): string
    {
        $attributes['name'] ??= $this->name;
        $attributes['class'] ??= 'form-control';

        $label = '';
        if (isset($attributes['label'])) {
            $attributes['placeholder'] ??= $attributes['label'];

            $attributes['id'] ??= $this->name;
            $label = sprintf('<label for="%s">%s</label>', $attributes['id'], $attributes['label']);
            unset($attributes['label']);
        }

        return $label . '<textarea ' . $this->htmlAttributes($attributes) . '>' . (string)$this . '</textarea>';
    }

    public function select(array $options, ...$attributes): string
    {
        $attributes['name'] ??= $this->name;

        $label = '';
        if (isset($attributes['label'])) {
            $attributes['id'] ??= $this->name;
            $label = sprintf('<label for="%s">%s</label>', $attributes['id'], $attributes['label']);
            unset($attributes['label']);
        }

        $attributes['value'] ??= $this->value;
        $value = $attributes['value'];
        unset($attributes['value']);

        return $label . '<select' . $this->htmlAttributes($attributes) . '>' . implode(array_map(
            fn ($key) => sprintf('<option value="%s"%s>%s</option>', $key, ($value===$key) ? ' selected' : '', $options[$key]),
            array_keys($options)
        )) . '</select>';
    }

    protected function htmlAttributes(array $attributes): string {
        return implode(array_map(function ($key) use ($attributes) {
            if ($attributes[$key] === false) {
                return '';
            }
            // CamelCase to kebab-case
            $attrName = strtolower(preg_replace('/[A-Z]/', '-$0', $key));
            if ($attributes[$key] === true) {
                return ' ' . $attrName;
            }
            return ' ' . $attrName . '="' . $attributes[$key] . '"';
        }, array_keys($attributes)));
    }
}
