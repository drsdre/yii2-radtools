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