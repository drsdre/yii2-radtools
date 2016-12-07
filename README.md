Yii2 RAD Tools
==============
Rapid Application Development tools to quickly build interconnected crud UI. Uses yii2-ajaxcrud to generate the pop-up forms and kartik-v Grid.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist drsdre/yii2-radtools "*"
```

or add

```
"drsdre/yii2-radtools": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, simply use it in your code by  :

```php
<?= \drsdre\radtools\AutoloadExample::widget(); ?>```