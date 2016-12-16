Yii2 RAD Tools
==============
Rapid Application Development tools to quickly build interconnected crud UI's. 
It uses yii2-ajaxcrud to generate the pop-up forms and optionally kartik-v/Dynagrid for the Gridview.

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

Once the extension is installed, simply extend from BaseAjaxCrudController and add settings:

```php
class UserController extends drsdre\radtools\BaseAjaxCrudController
{
    protected $useDynagrid = true;
    protected $modelClass = 'app\models\UserForm';
    protected $searchModelClass ='app\models\search\UserSearch';
    protected $model_name = 'User';
    protected $model_field_name = 'username';
```

To use hierarchy links, simply extend from AjaxCrudHierarchyLinkController and setup $hierarchy_links parameter:
```php
class TableController extends drsdre\radtools\AjaxCrudHierarchyLinkController
{   
    protected $useDynagrid = true;
    protected $modelClass = 'app\models\UserForm';
    protected $searchModelClass ='app\models\search\UserSearch';
    protected $model_name = 'User';
    protected $model_field_name = 'username';       
    protected $hierarchy_links = [
    		'user_id' => [
    			'model' => 'app\models\User',
    			'linked_model' => 'user',
    			'breadcrumbs' => [
    				[
    					'label' => 'Users',
    					'url' => '/user/overview',
    				],
    				[
    					'label' => '{model_name}',
    					'name_field' => 'username',
    					'url' => '/user/view?id={id}',
    				]
    			]
    		]
    	];
```

TODO add explanations for the parameters.