<?php
declare(strict_types=1);
namespace Hengeb\Simplates;

/**
 * @author Henrik Gebauer <code@henrik-gebauer.de>
 * @license https://opensource.org/license/mit MIT
 */

/**
 * template engine
 */
class Engine
{
    public string $proxyClass = TemplateVariable::class;

    /**
     * a context is a tuple ['extendedTemplate', 'extendTemplateVariables', 'variables']
     */
    protected $contexts = [];

    public function __construct(
        protected string $templatesDir
    )
    {
        $this->contexts = [
            [
                'extendedTemplate' => null,
                'extendTemplateVariables' => [],
                'variables' => [],
            ],
        ];
    }

    /**
     * safely escape html string
     */
    public static function htmlEscape(mixed $string): string
    {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * stores a variable
     */
    public function set(string $var, mixed $val): void
    {
        $this->getContext()['variables'][$var] = $val;
    }

    /**
     * get a variable (raw)
     */
    public function get(string $var): mixed
    {
        return $this->getContext()['variables'][$var] ?? null;
    }

    /**
     * get the current context
     */
    public function &getContext(): array
    {
        return $this->contexts[count($this->contexts) - 1];
    }

    /**
     * check if a template variable is set and truish
     */
    public function check(&$variable): bool
    {
        if (empty($variable)) {
            return false;
        } elseif ($variable instanceof TemplateVariable) {
            return $variable->isTrue();
        } else {
            return boolval($variable);
        }
    }

    /**
     * render a template and return the rendered template
     *
     * @param string $templateName name of the template without extension
     * @param array $variables (optional) variables in the scope of the template
     * @param mixed &$returnValue (optional) value may be set by the template, e.g. subject of a generated mail
     * @throws \UnexpectedValueException if the template does not exist
     */
    public function render(string $templateName, array $variables = [], mixed &$returnValue = null)
    {
        $this->contexts[] = [
            'extendedTemplate' => '',
            'extendedTemplateVariables' => [],
            'variables' => $variables
        ];

        $templateFilename = $this->templatesDir . "/$templateName";
        foreach (['.php' => 'html', '.tpl.php' => 'html', '.txt.php' => 'raw'] as $extension=>$escapeType) {
            if (is_file($templateFilename . $extension)) {
                break;
            }
        }
        if (!is_file($templateFilename . $extension)) {
            throw new \UnexpectedValueException("the template $templateName does not exist.", 1493681481);
        }

        $allVariables = array_merge(...array_column($this->contexts, 'variables'));
        // create proxies and extract them as local variables
        foreach ($allVariables as $key=>$value) {
            $$key = $this->proxyClass::create($key, $value, $escapeType, $this);
        }

        $this->startRecording();
        include $templateFilename . $extension;
        $contents = $this->stopRecording();

        $context = $this->getContext();

        if ($context['extendedTemplate']) {
            $contents = $this->render($context['extendedTemplate'], [
                ...['_contents' => $contents],
                ...$context['extendedTemplateVariables'],
            ]);
        }

        array_pop($this->contexts);
        return $contents;
    }

    /**
     * stop output buffering
     */
    public function stopRecording(): string
    {
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }

    /**
     * start output buffering
     */
    public function startRecording()
    {
        ob_start();
    }

    /**
     * call this from within a template with $this->extends if the template extends another one
     */
    protected function extends(string $templateName, array $variables = []) {
        $this->getContext()['extendedTemplate'] = $templateName;
        $this->getContext()['extendedTemplateVariables'] = $variables;
    }

    /**
     * include a template (call this from within a template as $this->include)
     */
    protected function include($templateName, array $variables = []): void {
        echo $this->render($templateName, $variables);
    }
}
