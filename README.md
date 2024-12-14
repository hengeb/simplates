# simplates

Simplates as a template engine for PHP for lightweight templates with automatic escaping

## basic usage

Your PHP file:

```php
$templateEngine = new \Hengeb\Simplates\Engine('/path/to/your/templates');
echo $templateEngine->render('user/profile', [
    'name' => 'Bob',
    'hobbys' => '<div style="position:absolute;display:grid;place-items:center;height:100dvh;background:black;inset:0;color:limegreen;font-size:400%">Your page is now mine<!--',
]);
```

And in `/path/to/your/templates/user/profile.php` (or `/path/to/your/templates/user/profile.tpl.php` if you prefer) put:

```php
<h3><?=$name></h3>

<div>My Hobbys are: <?=$hobbys?></div>
```

This will be rendered like this:

```php
<h3>Bob</h3>

<div>My Hobbys are: &lt;div style=&quot;position:absolute;display:grid;place-items:center;height:100dvh;background:black;inset:0;color:limegreen;font-size:400%&quot;&gt;Your page is now mine&lt;!--</div>
```

The strings are escaped automatically but object methods and properties can be accessed.

You can use the raw method of a variable to access the raw data:

```php
<?=$hobbys->raw()?> or just: <?=$hobbys->raw?>
```

### no escaping in text output

If, in the example above, you had saved your template as `/path/to/your/templates/user/profile.txt.php` (note the `.txt` in the file name), then no auto-escaping takes place but you can still use all the other features of template variables.

### advanced auto-escaping feature

Object properties and return values will be auto-escaped as well. Variables can be arrays as well.

A more advaned example:

```php
class User {
    public function __construct(public string $name, public string $hobbys)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHobbys(): string
    {
        return $this->hobbys;
    }
}

echo $templateEngine->render('user/list', [
    'users' => [
        new User('Alice', 'Coding'),
        new User('Bob', '<script>hijackPage();</script>'),
    ],
]);
```

And in your template:

```php
<?php if(count($users) === 0): ?>
    <p>There are no users to show.</p>
<?php else: ?>
    <table>
        <tr><th>Name</th><th>Hobbys</th></tr>
        <?php foreach($users as $user): ?>
            <tr><td><?=$user->getName()?>></td><td><?=$user->getHobbys()?></tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
```

This will render like this (white space has been modified in the result):

```html
<table>
    <tr><th>Name</th><th>Hobbys</th></tr>
    <tr><td>Alice</td><td>Coding</td></tr>
    <tr><td>Bob</td><td>&lt;script&gt;hijackPage();&lt;/script&gt;</td></tr>
</table>
```

## Dates and times

```php
echo $templateEngine->render('now', [
    'now' => new \DateTimeImmutable('now'),
    '_timeZone' => new \DateTimeZone('Europe/Berlin'),
]);
```

and in your template `now.tpl.php`:

```php
<div>current date: <?=$now->format('d.m.Y')?></div>
```

result:

```html
<div>current date: 14.12.2024</div>
```

The date is converted to the provided time zone (variable named `_timeZone`). If you do not provide a time zone, the time zone will not be changed.

Another way is to provide the timezone name in the call of the `format` method:

```php
<div>current date: <?=$now->format('d.m.Y', 'Europe/Berlin`)?></div>
```

## global variables

In the examples above all the variables are passed in the context of the template. If a variable is used in many places you can set a global template variable like this:

```php
$templateEngine->set('currentUser', $currentUser);
$templateEngine->set('_timeZone', new \DateTimeZone('Europe/Berlin'));
```

## Reusing templates

You can use the `extends` and `include` methods to reuse templates:

In your template, use:

```php
<?php $this->extends('layout', ['title' => $user->getName()]); ?>

<?php $this->include('partials/alert', ['message' => 'Your data has been saved.']); ?>

Hello, <?=$name?>!
```

And in `layout.php` put:

```php
<!DOCTYPE html>
<html>
    <head>
        <title><?=$title->raw?></title>
    </head>
    <body>
        <h1><?=$title->raw?></h1>
        <?=$_contents->raw?>
    </body>
</html>
```

And in `partials/alert.php` put:

```php
<div class="alert"><?=$message></div>
```

Result:

```html
<!DOCTYPE html>
<html>
    <head>
        <title>Bob</title>
    </head>
    <body>
        <h1>Bob</h1>
<div class="alert">Your data has been saved.</div>
Hello, Bob!
    </body>
</html>
```

## return values

You can return values from your template content to the calling context.

For example, if you have a template for an email you might want to use your template to generate the subject and return it:

In your template `mail/confirm.txt.php`:

```php
<?php
$returnValue = $subject = 'Please confirm your email address';
?>
Hello <?=$name?>,

Please go to <?=$url?> to confirm your email address.
```

And in the calling context:

```php
$body = $templateEngine->render('mail/confirm', [
    'name' => $name,
    'url' => $url,
], $subject);
$mailService->send($address, $subject, $body);
```

## falsish values

The auto-escaping feature comes with the drawback that falsish variables cannot be checked with `if ($value)` or `if (!empty($value))` because in PHP, objects are always truish and technically, all the template variables are object (of the `TemplateVariable` class).

Use the engine's `check`, the variable's `isTrue` or `isEmpty` methods or other means instead:

instead of:

```php
<?php if ($array): ?>
<?php if ($isError): ?>
<?php if ($name): ?>
<?php if (!empty($mightNotBeSet)): ?>
<?php if ($checkboxValue): ?>
```

use

```php
<?php if ($this->check($array)): ?> or <?php if ($array-isTrue()): ?> or <?php if (!$array-isEmpty()): ?> or <?php if (count($array)): ?>
<?php if ($this->check($isError)): ?> or <?php if ($isError-isTrue()): ?> or <?php if (!$isError-isEmpty()): ?>
<?php if ($this->check($name)): ?> or <?php if ($name-isTrue()): ?> or <?php if (!$name-isEmpty()): ?> or <?php if ("$name"): ?> or <?php if ($name != ""): ?> or <?php if ("$name" !== ""): ?>
<?php if ($this->check($mightNotBeSet)): ?>
<?php if ($this->check($checkboxValue)): ?> or <?php if ($checkboxValue-isTrue()): ?> or <?php if (!$checkboxValue-isEmpty()): ?>
```

the suggested means are:

```php
<?php if ($this->check($array)): ?> or <?php if (count($array)): ?>
<?php if ($this->check($isError)): ?> or <?php if ($isError-isTrue()): ?>
<?php if ($this->check($name)): ?> or <?php if ("$name"): ?> or <?php if ($name != ""): ?> or <?php if ("$name" !== ""): ?>
<?php if ($this->check($mightNotBeSet)): ?>
<?php if ($this->check($checkboxValue)): ?> or <?php if ($checkboxValue-isTrue()): ?>
```

## built-in methods of template variables

Template variables come with some handy methods that make writing templates cleaner. Use them like this: `<?=$data->json()?>`

* `escape()`: escape HTML special characters (only needed in non-auto-escaping contexts)
* `raw()`: do not escape  (only needed in auto-escaping contexts)
* `json(bool $pretty = false)`: JSON representation of the data. Use like this:
* `htmlTable()`: return HTML table of the array content
* `htmlList(string $listType = 'ol')`: return HTML list of array contents
* `dump()`: wrap value in `<pre>` tag
* `implode(string $separator = ', ')`: implode array list
* `inputHidden(string $name = '')`: create HTML input element of type=hidden
* `input(...$attributes)`: create HTML input element of type=text (default) or similar types like type=date (call like this: `$date->input(type: 'date')`), you can also add a label with a `lable` attribute.
* `box(...$attributes)`: create HTML input element of type=checkbox (default) or type=radio, you can also add a label with a `lable` attribute.
* `textarea(...$attributes)`: create HTML textarea element.
* `select(array $options, ...$attributes)`: create HTML select element. The `$options` parameter is an array like `[value => label, ...]`

Note that methods of the actual object that is stored in the template variable are a bit harder to access if their name coincidentally matches one of the built-in methods of the template variable proxy class. You would have to use the `__call` method in this case: `<?=$variable->__call('input', [$arguments])?>`

## extend the functionality

You can extend the `TemplateVariable` class and add your own methods. You have to extend the `Engine` class as well so it uses your class:

```php
class TemplateVariable extends \Hengeb\Simplates\TemplateVariable
{
    public function bold(): string
    {
        return "<span style='font-weight:bold'>" . $this->__toString() . "</span>";
    }
}

// connect the extension to the engine
\Hengeb\Simplates\Engine::$proxyClass = TemplateVariable::class;
```

With this extended template engine, every template variable has a bold() method:

```php
Hello, <?=$name->bold()?>!
```

This will render like this:

```html
Hello, <span style='font-weight:bold'>Bob</span>!
```

Of course, you can also extend the `Engine` class itself to provide extra features.