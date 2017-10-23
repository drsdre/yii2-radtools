Yii2 RAD Tools
==============

[![Build Status](https://travis-ci.org/drsdre/yii2-radtools.svg?branch=master)](https://travis-ci.org/drsdre/yii2-radtools)

Rapid Application Development controller for quick and complete crud interfaces
to linked database models. 
It supports [yii2-ajaxcrud](https://github.com/johnitvn/yii2-ajaxcrud) for modal based crud forms (pop-ups),
 [kartik-v/Dynagrid](https://github.com/kartik-v/yii2-dynagrid) for the Gridview 
and [kartik-v/yii2-detail-view](https://github.com/kartik-v/yii2-detail-view) for integrated view/update/create forms.

The controller comes with the following build-in actions:
* index: full page using build in or kartik-v GridView
* view: either full page or using yii2-ajaxcrud modal
* create: either full page or using yii2-ajaxcrud modal
* copy: either full page or using yii2-ajaxcrud modal
* update: either full page or using yii2-ajaxcrud modal
* delete: either full page or using yii2-ajaxcrud modal
* bulkUpdate: for bulk actions from GridView
* bulkDelete: for bulk actions from GridView

All actions can be configured to have specific success URL's and custom variables to be send to the view. 
When a 'return_url' GET parameter with the action, it will overrule the success URL.

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
    protected $useDetailView = true;
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

Bulk update & delete
-----

The bulk-update and bulk-delete actions are enabled by default in BaseAjaxCrudController. 
They are added in the view using the BulkButtonWidget. 

Bulk-update uses the model scenario that can be set on a single update action. 
You can add form elements for changing the data to the 'data-confirm-message'. 
Use the exact field name of the model (this is auto-mapped in the action). 
The 'data-confirm-ok' field is used to build the submit button which pushes. 

For example:
  
```php
   <?= DynaGrid::widget([
                'options' => [
                    'id' => 'example-gridview',
                ],
                'gridOptions'=>[
                    'dataProvider' => $dataProvider,
                    'filterModel' => $searchModel,
                    'panel' => [
                        'type' => 'primary',
                        'heading' => '<i class="glyphicon glyphicon-list"></i>',
                        'before' => '<em></em>',
                        'after' => BulkButtonWidget::widget( [
		                        'buttons' =>
			                        Html::a( '<i class="glyphicon glyphicon-pencil"></i>&nbsp;Change Status',
				                        [ '/cmm-wptheme-map/bulk-update' ],
				                        [
					                        'class'                => "btn btn-primary btn-xs",
					                        'role'                 => 'modal-remote-bulk',
					                        'data-method'          => false,// for overide yii data api
					                        'data-request-method'  => 'post',
					                        'data-confirm-title'   => 'Bulk Change Status',
					                        'data-confirm-message' =>
						                        Html::dropDownList(
							                        'status',
							                        '',
							                        common\models\Model::$statuses
						                        ),
					                        'data-confirm-ok '     => Html::button( 'Save',
						                        [ 'class' => 'btn btn-primary', 'type' => "submit" ] ),
				                        ]
			                        ) .
			                        ' ' .
			                        Html::a( '<i class="glyphicon glyphicon-trash"></i>&nbsp; Delete All',
				                        [ "bulk-delete" ],
				                        [
					                        'class'                => "btn btn-danger btn-xs",
					                        'role'                 => 'modal-remote-bulk',
					                        'data-confirm'         => false,
					                        'data-method'          => false,
					                        'data-request-method'  => 'post',
					                        'data-confirm-title'   => 'Are you sure?',
					                        'data-confirm-message' => 'Are you sure want to delete this item',
				                        ] ),
	                        ] ) .
                                   '<div class="clearfix"></div>',
                    ],
                ],
                'columns' => require(__DIR__.'/_columns.php'),
            ]); ?>
```
TODO add explanations for the parameters.