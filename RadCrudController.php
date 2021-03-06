<?php
/**
 * Rapid Application Development Crud Controller
 * Should be extended from in your frontend controller for default CRUD actions.
 *
 * By: A.F.Schuurman
 * Date: 2016-12-07
 */

namespace drsdre\radtools;

use yii;
use yii\web\Controller;
use yii\db\ActiveRecord;
use yii\web\NotFoundHttpException;
use yii\helpers\Html;
use yii\web\Response;
use yii\helpers\ArrayHelper;
use drsdre\radtools\helpers\Url;

/**
 * Implements Ajax CRUD actions for a model.
 *
 * @property $gridviewPjaxId string
 * @property $modelId string
 * @property $modelObjectName string
 */
class RadCrudController extends Controller {

	/** @var ActiveRecord $modelClass class name of CRUD record with namespace [required] */
	protected $modelClass;

	/** @var ActiveRecord $searchModelClass class name of ActiveRecord used for searching [required] */
	protected $searchModelClass;

	/** @var string $model_name displayed type name of record [required] */
	protected $model_name;

	/** @var string $model_field_name ActiveRecord field name used to display name of record [required] */
	protected $model_field_name;

	/** @var string $model_id_field ActiveRecord field name containing id of record */
	protected $model_id_field = 'id';

	/** @var string $grid_persistent_reset_param name of query parameter for resetting persistent cache */
	static $grid_persistent_reset_param = 'reset_grid_persistence';

	/** @var string $persist_grid_session_key key id for caching persistent parameters */
	static $persist_grid_session_key = 'AjaxCrudDP';

	/** @var bool $persist_grid_filters to persist grid filter or not */
	protected $persist_grid_filters = false;

	/** @var bool $persist_grid_page to persist grid page or not */
	protected $persist_grid_page = false;

	/** @var bool $persist_grid_order to persist grid order or not */
	protected $persist_grid_order = false;

	/** @var bool $useDynagrid set to true if Kartik-v yii2-dynagrid is used for index */
	protected $useDynagrid = false;

	/** @var bool $useDetailView set to true if Kartik-v yii2-detail-view is used for view/update/create */
	protected $useDetailView = false;

	/** @var array|string $updateSuccessRedirect view to redirect to when update was successful */
	protected $updateSuccessRedirect = [ 'view' ];

	/** @var array|string $createSuccessRedirect default url to redirect to when create was successful */
	protected $createSuccessRedirect = [ 'view' ];

	/** @var array|string $copySuccessRedirect default url to redirect to when copy was successful */
	protected $copySuccessRedirect = [ 'view' ];

	/** @var array|string $copySuccessRedirect default url to redirect to when copy was successful */
	protected $deleteSuccessRedirect = [ 'index' ];

	/** @var array|string $bulkDeleteSuccessRedirect default url to redirect to when bulk delete was successful */
	protected $bulkDeleteSuccessRedirect = [ 'index' ];

	/** @var string $bulkUpdateSuccessRedirect default url to redirect to when bulk update was successful */
	protected $bulkUpdateSuccessRedirect = [ 'index' ];

	/** @var string $viewShowFullpageLink when true, adds a clickable icon in modal view to switch to full page view */
	protected $viewShowFullpageLink = false;

	/** @var ActiveRecord $model current selected record for CRUD operations */
	protected $model;

	/**
	 * @event BeforeCrudValidation an event that is triggered before a crud action is validated.
	 */
	const EVENT_BEFORE_CRUD_VALIDATION = 'beforeCrudValidation';

	/**
	 * @event AfterCrudEvent an event that is triggered after a crud action is completed.
	 */
	const EVENT_AFTER_CRUD_SUCCESS = 'afterCrudSuccess';

	public function init()
	{
		parent::init();
		$this->registerTranslations();
	}

	public function registerTranslations()
	{
		$i18n = Yii::$app->i18n;
		$i18n->translations['radtools'] = [
			'class' => 'yii\i18n\PhpMessageSource',
			'sourceLanguage' => 'en-US',
			'basePath' => '@vendor/drsdre/yii2-radtools/messages',
			'fileMap' => [
				'radtools' => 'radtools.php',
			],
		];
	}

	// Actions
	// ----------------------------------

	/**
	 * Lists all records (for gridview)
	 *
	 * @return array|mixed|string|Response
	 * @throws yii\base\InvalidConfigException
	 */
	public function actionIndex() {

		// Setup page title and first breadcrumb
		$this->view->title = yii::t( 'radtools', '{object} Overview', [
			'object' => $this->model_name,
		] );
		$this->addBreadCrumbs( [ $this->view->title ] );

		// Setup data feed
		$searchModel  = new $this->searchModelClass();
		$dataProvider = $this->indexDataProvider( $searchModel );

		$this->beforeCrudValidation(
			$this->action->id,
			$this->model
		);

		return $this->render( 'index', $this->indexRenderData( $searchModel, $dataProvider ) );
	}

	/**
	 * View model record.
	 *
	 * @param integer $id
	 *
	 * @return array|mixed|string|Response
	 * @throws NotFoundHttpException
	 * @throws yii\base\InvalidConfigException
	 */
	public function actionView( int $id ) {

		$this->findModel( $id );

		// Setup page title and first breadcrumb
		$this->view->title = $this->getModelObjectName();
		$this->addBreadCrumbs( [
			[ 'label' => $this->model_name . ' Overview', 'url' => [ 'index' ] ],
			ArrayHelper::getValue( $this->model, $this->model_field_name ),
		] );

		$this->beforeCrudValidation(
			$this->action->id,
			$this->model
		);

		// Updates from DetailView
		if ( $this->useDetailView ) {

			// Set a model scenario if specified
			if ( isset( $this->model_update_scenario ) ) {
				$this->model->setScenario( $this->model_update_scenario );
			}

			// Save the data
			if ( $this->saveFormModel() ) {
				// Save success
				yii::$app->session->setFlash(
					'kv-detail-success',
					yii::t( 'radtools', 'Saved successfully' )
				);
				return $this->afterCrudSuccess(
					$this->action->id,
					$this->model,
					$this->redirect( [ 'view', 'id' => $this->model->{$this->model_id_field} ] )
				);
			} elseif ( $this->model->getDirtyAttributes() ) {
				// Flash validation error(s)
				yii::$app->session->setFlash(
					'kv-detail-error',
					yii::t( 'radtools', 'Error(s) saving' )
				);
			}
		}

		// Render crud response
		return $this->crudActionResponse(
			'view',
			$this->viewRenderData(),
			$this->modalToFullpageLink( 'view' ) . $this->view->title,
			$this->viewModalFooter()
		);
	}

	/**
	 * Creates a new model.
	 *
	 * @return array|mixed|string|Response
	 * @throws yii\base\InvalidConfigException
	 */
	public function actionCreate() {
		// Setup a new record with default values
		$this->model = $this->newModel();
		$this->model->loadDefaultValues();

		// Setup page title and first breadcrumb
		$this->view->title = yii::t( 'radtools', 'Create New {model_name}', [
			'model_name' => $this->model_name,
		] );
		$this->addBreadCrumbs( [
			[ 'label' => $this->model_name . ' Overview', 'url' => [ 'index' ] ],
			yii::t( 'radtools', 'Create' ),
		] );

		// Set a model scenario if specified
		if ( isset( $this->model_create_scenario ) ) {
			$this->model->setScenario( $this->model_create_scenario );
		}

		$this->beforeCrudValidation(
			$this->action->id,
			$this->model
		);

		// Load, validate and save model data
		if ( ! yii::$app->request->isGet && $this->saveFormModel() ) {
			// Success
			return $this->crudActionSuccessResponse(
				$this->createRenderData(),
				$this->createModalFooterSaved(),
				yii::t( 'radtools', '{model_name} {model_object_name} created', [
					'model_name'        => $this->model_name,
					'model_object_name' => $this->getModelObjectName(),
				] ),
				$this->createSuccessRedirect
			);
		}

		// Render crud response
		return $this->crudActionResponse(
			'create',
			$this->createRenderData(),
			$this->view->title,
			$this->createModalFooterEdit()
		);
	}

	/**
	 * Copy from a new model.
	 *
	 * @param int $id
	 *
	 * @return array|mixed|string|Response
	 * @throws NotFoundHttpException
	 * @throws yii\base\InvalidConfigException
	 */
	public function actionCopy( int $id ) {
		$this->findModel( $id );

		// Mark record as new
		$this->model->id          = null;
		$this->model->isNewRecord = true;

		// Setup page title and first breadcrumb
		$this->view->title = yii::t( 'radtools', 'Copy {model_object_name}',
			[ 'model_object_name' => $this->getModelObjectName() ]
		);

		$this->addBreadCrumbs( [
			[ 'label' => $this->model_name . ' Overview', 'url' => [ 'index' ] ],
			yii::t( 'radtools', 'Copy' ),
		] );

		// Set a model scenario if specified
		if ( isset( $this->model_create_scenario ) ) {
			$this->model->setScenario( $this->model_create_scenario );
		}

		$this->beforeCrudValidation(
			$this->action->id,
			$this->model
		);

		// Load, validate and save model data
		if ( ! yii::$app->request->isGet && $this->saveFormModel() ) {
			// Success
			return $this->crudActionSuccessResponse(
				$this->updateRenderData(),
				$this->createModalFooterSaved(),
				yii::t( 'radtools', '{model_name} {model_object_name} copied from {id}', [
					'model_name'        => $this->model_name,
					'model_object_name' => $this->getModelObjectName(),
					'id'                => $id,
				] ),
				$this->copySuccessRedirect
			);
		}


		// Render crud response
		return $this->crudActionResponse(
			'create',
			$this->createRenderData(),
			$this->view->title,
			$this->createModalFooterEdit()
		);
	}

	/**
	 * Updates an existing model.
	 *
	 * @param integer $id model ID
	 *
	 * @return array|mixed|string|Response
	 * @throws NotFoundHttpException
	 * @throws yii\base\InvalidConfigException
	 */
	public function actionUpdate( int $id ) {
		$this->findModel( $id );

		// Setup generic view settings
		$this->view->title = yii::t( 'radtools', 'Update {model_object_name}', [
			'model_object_name' => $this->getModelObjectName(),
		] );
		$this->addBreadCrumbs( [
			[ 'label' => $this->model_name . ' Overview', 'url' => [ 'index' ] ],
			[
				'label' => ArrayHelper::getValue( $this->model, $this->model_field_name ),
				'url'   => [ 'view', 'id' => $this->model->{$this->model_id_field} ],
			],
			yii::t( 'yii', 'Update' ),
		] );

		// Set a model scenario if specified
		if ( isset( $this->model_update_scenario ) ) {
			$this->model->setScenario( $this->model_update_scenario );
		}

		$this->beforeCrudValidation(
			$this->action->id,
			$this->model
		);

		// Load, validate and save model data
		if ( ! yii::$app->request->isGet && $this->saveFormModel() ) {
			// Success
			return $this->crudActionSuccessResponse(
				$this->updateRenderData(),
				$this->updateModalFooterSaved(),
				yii::t( 'radtools', '{model_name} {model_object_name} updated', [
					'model_name'        => $this->model_name,
					'model_object_name' => $this->getModelObjectName(),
				] ),
				$this->updateSuccessRedirect
			);
		}

		// Render crud response
		return $this->crudActionResponse(
			'update',
			$this->updateRenderData(),
			$this->view->title,
			$this->updateModalFooterEdit()
		);
	}

	/**
	 * Deletes an existing model.
	 *
	 * @param integer $id model ID
	 *
	 * @return array|mixed|string|Response
	 *
	 * @throws NotFoundHttpException
	 * @throws yii\base\InvalidConfigException
	 */
	public function actionDelete( int $id = null ) {
		// If detail view, try to get id from custom_param
		if ($this->useDetailView ) {
			$id = yii::$app->request->post('id', $id);
		}

		$this->findModel( $id );

		// Setup URL to return after success
		$return_url = yii::$app->request->get( 'return_url', $this->deleteSuccessRedirect );

		// Determine if request is from modal
		$modal_request = yii::$app->request->isAjax && ! yii::$app->request->isPjax;
		$ajax_response = null;

		$this->beforeCrudValidation(
			$this->action->id,
			$this->model
		);

		// Delete the record
		$delete_result = $this->deleteModel();

		// Check for error message
		$error_msg = false;
		$succes_msg = '';
		if ( is_string( $delete_result ) ) {
			$error_msg = yii::t( 'radtools', 'Cannot delete {model_object_name}: {error}',
				[
					'model_object_name' => $this->getModelObjectName(),
					'error'             => $delete_result,
				]
			);
			// Check for result false
		} elseif ( $delete_result === false ) {
			$error_msg = yii::t( 'radtools', 'Cannot delete {model_object_name}',
				[ 'model_object_name' => $this->getModelObjectName() ]
			);
		} else {
			$succes_msg = yii::t( 'radtools', '{model_object_name} deleted',
				[ 'model_object_name' => $this->getModelObjectName() ]
			);
		}


		// Ajax responses

		// DetailView request
		if ( $this->useDetailView ) {
			// Ajax request
			yii::$app->response->format = Response::FORMAT_JSON;

			if ( $error_msg ) {
				return [
					'success'  => false,
					'messages' => [
						'kv-detail-error' => $error_msg,
					],
				];
			} else {
				$this->afterCrudSuccess(
					$this->action->id,
					null,
					[
						'success'  => true,
						'messages' => [
							'kv-detail-info' => $succes_msg . ' ' .
							                    Html::a(
								                    yii::t( 'radtools', 'Return' ),
								                    $return_url,
								                    [ 'class' => 'btn btn-sm btn-info' ]
							                    ),
						],
					]
				);
			}
		}

		// Ajax Crud modal request
		if ( $modal_request ) {
			yii::$app->response->format = Response::FORMAT_JSON;

			// If error_msg, send error message to modal
			if ( $error_msg ) {
				return [
					'title'   => yii::t( 'radtools', 'Delete failed' ),
					'content' => $error_msg,
					'footer'  => Html::button( 'Close', [
						'class'        => 'btn btn-default pull-left',
						'data-dismiss' => 'modal',
					] ),
				];
			} else {
				$return_view = is_array( $return_url ) ? $return_url[0] : $return_url;

				if ( $return_view == 'index' ) {
					// Back to grid: reload grid and close modal
					$ajax_response = [
						'forceReload' => $this->getGridviewPjaxId(),
						'forceClose'  => true,
					];
				} else {
					// A different return URL: redirect to this URL
					$ajax_response = [
						'forceRedirect' => Url::to( $return_url ),
					];
				}

				// Trigger crud success action and return to view
				return $this->afterCrudSuccess(
					$this->action->id,
					$this->model,
					$ajax_response
				);
			}
		}


		// Default page based request

		if ( $error_msg ) {
			// Set error flash
			yii::$app->session->setFlash( 'alert', [
				'body'    => $error_msg,
				'options' => [ 'class' => 'alert-danger' ],
			] );

			return $this->redirectReturnUrl( $return_url );
		} else {
			// Set success flash
			yii::$app->session->setFlash( 'alert', [
				'body'    => $succes_msg,
				'options' => [ 'class' => 'alert-success' ],
			] );

			// Trigger crud success action and return to view
			return $this->afterCrudSuccess(
				$this->action->id,
				$this->model,
				$this->redirectReturnUrl( $return_url )
			);
		}
	}

	/**
	 * Delete multiple existing model.
	 * For ajax request will return json object
	 *
	 * @return array|mixed|string|Response
	 *
	 * @throws NotFoundHttpException
	 * @throws \Exception
	 */
	public function actionBulkDelete() {
		$errors = [];
		$record_count = 0;

		// For all given id's (pks)
		$pks = explode( ',', yii::$app->request->post( 'pks' ) );
		foreach ( $pks as $pk ) {
			// Get the model and delete
			$this->findModel( $pk, false );

			// Check if model is found
			if ( ! $this->model ) {
				$errors[ $pk ] = yii::t( 'radtools', 'Record not found' );
				continue;
			}

			// Try to delete model
			$delete_result = $this->deleteModel();

			// Check if model is deleted
			if ( $this->model ) {
				// Check for validation errors
				if ( $this->model->getErrors() ) {
					$errors[ $pk ] = Html::errorSummary(
						$this->model,
						[
							'header' =>
								yii::t(
									'radtools',
									'Delete \'{object}\' error:',
									[
										'object' => ArrayHelper::getValue( $this->model, $this->model_field_name ),
									] )
						]
					);
					continue;
				}
				// Check for delete_result message
				elseif ( is_string( $delete_result ) ) {
					$errors[ $pk ] = yii::t(
						'radtools',
						'Cannot delete {model_object_name}: {result}',
						[
							'model_object_name' => $this->getModelObjectName(),
							'result'            => $delete_result,
						] );
				}
				// General error
				else {
					$errors[ $pk ] = yii::t(
						'radtools',
						'Cannot delete {model_object_name}: reason unknown',
						[
							'model_object_name' => $this->getModelObjectName(),
						] );
				}
			}

			$record_count ++;
		}


		return $this->bulkActionResponse(
			yii::t( 'radtools', 'Bulk Delete' ),
			yii::t( 'radtools', '{record_count,plural,=0{No} =1{One} other{#}} {mobel_name} deleted',
				[
					'mobel_name'   => $this->model_name,
					'record_count' => $record_count,
				] ),
			$this->bulkDeleteSuccessRedirect,
			$errors
		);
	}

	/**
	 * Process bulk updates
	 * For ajax request will return json object
	 *
	 * @return array|mixed|string|Response
	 * @throws NotFoundHttpException
	 * @throws yii\base\InvalidConfigException
	 */
	public function actionBulkUpdate() {
		// Parse the fields to update
		$update_attribute_value = [];

		/** @var ActiveRecord $model */
		$model                  = new $this->modelClass;

		if ( isset( $this->model_update_scenario ) ) {
			$model->setScenario( $this->model_update_scenario );
		}

		/** @var ActiveRecord $model */
		foreach ( $model->activeAttributes() as $attribute ) {
			// Check if a value is provided
			if ( ! is_null( yii::$app->request->post( $attribute ) ) ) {
				$update_attribute_value[ $attribute ] = yii::$app->request->post( $attribute );
			}
		}

		$errors = [];
		$records_updated = 0;

		// Check if there are fields to update
		if ( ! $update_attribute_value ) {
			$errors[] = yii::t( 'radtools', 'No fields found to update.' );
		} else {
			// Update the fields for all the selected records (pks)
			$pks = explode( ',', yii::$app->request->post( 'pks' ) );
			foreach ( $pks as $id ) {
				$this->findModel( (int) $id, false );

				// Check if model is found
				if ( ! $this->model ) {
					$errors[ $id ] = yii::t( 'radtools', 'Record {id} not found', [ 'id' => $id ] );
					continue;
				}

				// Set a model scenario if specified
				if ( isset( $this->model_update_scenario ) ) {
					$this->model->setScenario( $this->model_update_scenario );
				}

				// Update the variables
				$this->model->setAttributes( $update_attribute_value );

				// Check if record is changed
				if ( $this->model->getDirtyAttributes() ) {
					// Save data
					if ( ! $this->model->save() ) {
						// Track errors
						$errors[ $id ] = Html::errorSummary(
							$this->model,
							[
								'header' =>
									yii::t( 'radtools', 'Update \'{object}\' error:', [
										'object' => ArrayHelper::getValue( $this->model, $this->model_field_name )
									] )
							]
						);
					} else {
						// Track updates
						$records_updated ++;
					}
				}
			}
		}

		return $this->bulkActionResponse(
			yii::t( 'radtools', 'Bulk Update' ),
			yii::t( 'radtools', '{record_count,plural,=0{no records} =1{one record} other{# records}} updated', [
				'record_count' => $records_updated,
			] ),
			$this->bulkUpdateSuccessRedirect,
			$errors
		);
	}

	// Public non-action functions
	// --------------------------------------------------


	/**
	 * This method is invoked right before a crud action is validated.
	 *
	 * The method will trigger the [[EVENT_BEFORE_CRUD_VALIDATION]] event.
	 *
	 * If you override this method, your code should look like the following:
	 *
	 * ```php
	 * public function beforeCrudValidation($action, $model)
	 * {
	 *     parent::beforeCrudValidation($action, $model);
	 * }
	 * ```
	 *
	 * @param string $action the action just executed.
	 * @param ActiveRecord $model the model of the crud action.
	 */
	public function beforeCrudValidation( string $action, ActiveRecord $model = null ) {
		// Create the EVENT_BEFORE_CRUD_VALIDATION event
		$event = new RadCrudEvent($action);
		$event->model = $model;
		$this->trigger(self::EVENT_BEFORE_CRUD_VALIDATION, $event);
	}

	/**
	 * This method is invoked right after a crud action is completed.
	 *
	 * The method will trigger the [[EVENT_AFTER_CRUD_SUCCESS]] event. The return value of the method
	 * will be used as the action return value.
	 *
	 * If you override this method, your code should look like the following:
	 *
	 * ```php
	 * public function afterCrudSuccess($action, $model, $result)
	 * {
	 *     $result = parent::afterCrudSuccess($action, $model, $result);
	 *     // your custom code here
	 *     return $result;
	 * }
	 * ```
	 *
	 * @param string $action the action just executed.
	 * @param ActiveRecord $model the model of the crud action.
	 * @param mixed $result the result of the crud action.
	 * @return mixed the processed action result.
	 */
	public function afterCrudSuccess( string $action, ActiveRecord $model = null, $result ) {
		// Create the EVENT_AFTER_CRUD_SUCCESS event
		$event = new RadCrudEvent($action);
		$event->model = $model;
		$event->result = $result;
		$this->trigger(self::EVENT_AFTER_CRUD_SUCCESS, $event);

		// Return/execute result
		return $result;
	}

	/**
	 * Add breadcrumbs to the view
	 *
	 * @param array $breadcrumbs
	 */
	public function addBreadCrumbs( array $breadcrumbs = [] ) {
		foreach ( $breadcrumbs as $crumb ) {
			$this->view->params['breadcrumbs'][] = $crumb;
		}
	}

	/**
	 * Model id for setting up css div id
	 *
	 * @return string
	 * @throws yii\base\InvalidConfigException
	 */
	public function getModelId() {
		if ( $this->useDynagrid ) {
			$searchModel = new $this->searchModelClass();
			/** @var ActiveRecord $searchModel */
			return $searchModel->formName();
		} else {
			// Default for yii2-ajaxcrud
			return 'crud';
		}
	}

	/**
	 * Delete current model
	 *
	 * @return false|int|string int = amount of deletes, false or string is failure
	 */
	public function saveFormModel() {
		return $this->model->load( yii::$app->request->post() ) &&
		       $this->model->save();
	}

	/**
	 * Delete current model
	 *
	 * @return false|int|string int = amount of deletes, false or string is failure
	 */
	public function deleteModel() {
		try {
			return $this->model->delete();
		} catch ( \Exception $e ) {
			// Build error message
			if ( $e->getCode() === 23000 && preg_match( '/CONSTRAINT `(.*)` FOREIGN/', $e->getMessage(),
					$matches ) === 1
			) {
				// Handle SQL foreign key errors
				return yii::t( 'radtools', 'The record is linked with {foreign_key}. Unlink before delete.',
					[ 'foreign_key' => $matches[1] ] );
			} else {
				return $e->getMessage();
			}
		} catch ( \Throwable $e ) {
			return $e->getMessage();
		}
	}

	/**
	 * Returns model object type and name
	 *
	 * @return string
	 */
	public function getModelObjectName() {
		return $this->model_name . ' ' . ArrayHelper::getValue( $this->model, $this->model_field_name );
	}

	/**
	 * Session key for filter persistence
	 *
	 * @return string
	 */
	public function dataProviderSessionKey() {
		return self::$persist_grid_session_key . '_' . self::class;
	}

	/**
	 * Setup a DataProvider with optional filter/page/order persistence
	 *
	 * @param ActiveRecord $searchModel
	 * @param string $grid_id          when multiple dataProvider widgets are used on a page
	 * @param array $base_where_filter base filter which is applied with ActiveQuery andWhere after search
	 * @param bool $persist_filters
	 * @param bool $persist_page
	 * @param bool $persist_order
	 *
	 * @return mixed
	 * @throws yii\base\InvalidConfigException
	 */
	public function setupDataProvider(
		ActiveRecord $searchModel,
		string $grid_id = '',
		array $base_where_filter = [],
		bool $persist_filters = false,
		bool $persist_page = false,
		bool $persist_order = false
	) {
		// Setup persistence parameters
		$session_key      = $this->dataProviderSessionKey() . $grid_id;
		$persistent_reset = yii::$app->request->get( $grid_id . self::$grid_persistent_reset_param, false );

		if ( $persist_filters ) {
			// Setup persistent filtering
			if ( $persistent_reset ) {
				// Clear query filters on filter reset
				yii::$app->session->remove( $session_key . '_filters' );

				Yii::debug( 'Persistent filters reset', __METHOD__ );
			} elseif ( ! yii::$app->request->get( $searchModel->formName(), false ) ) {
				// If no filters set in query, load persisted filters from session into search model
				$searchModel->setAttributes( yii::$app->session->get( $session_key . '_filters', [] ) );

				Yii::debug( 'Persistent filters read from session', __METHOD__ );
			} else {
				// If filtering changed, remove page persistence
				yii::$app->session->remove( $session_key . '_page' );

				Yii::debug( 'Persistent filters changed, clear persistent page', __METHOD__ );
			}
		}

		// Create dataProvider
		$dataProvider = $searchModel->search( yii::$app->request->queryParams );

		// Persist query filters from search
		if ( $persist_filters ) {
			yii::$app->session->set( $session_key . '_filters', $searchModel->getAttributes() );
		}

		// Apply default filters if provided
		if ( count( $base_where_filter ) ) {
			$dataProvider->query->andWhere( $base_where_filter );
		}

		// Setup query parameters (especially for sub-grids)
		$dataProvider->pagination->pageParam = $grid_id . '_page';
		$dataProvider->sort->sortParam       = $grid_id . '_sort';

		// Persistent paging
		if ( $persist_page ) {
			if ( $persistent_reset ) {
				// Reset parameter
				yii::$app->session->remove( $session_key . '_page' );

				Yii::debug( 'Persistent page reset', __METHOD__ );
			}

			// Get page number from query
			$page_number = yii::$app->request->get( $dataProvider->pagination->pageParam, false );

			// If page_number is not in query, use persisted page selection
			if ( $page_number === false ) {
				$page_number = yii::$app->session->get( $session_key . '_page', 0 );
				if ( $page_number <= $dataProvider->pagination->pageCount ) {
					$dataProvider->pagination->page = $page_number;

					Yii::debug( 'Persistent page ' . $page_number . ' read from session', __METHOD__ );
				}
			}

			// Set page number and persist it
			yii::$app->session->set( $session_key . '_page', $page_number );
		}

		// Persistent sorting
		if ( $persist_order ) {
			if ( $persistent_reset ) {
				// Reset parameter
				yii::$app->session->remove( $session_key . '_sorting' );

				Yii::debug( 'Persistent order reset', __METHOD__ );
			}

			// If no current order, use persisted order
			if ( ! $dataProvider->sort->getAttributeOrders() ) {
				$dataProvider->sort->setAttributeOrders( yii::$app->session->get( $session_key . '_sorting', [] ) );

				Yii::debug( 'Persistent order read from session', __METHOD__ );
			}

			// Persist the current ordering
			yii::$app->session->set( $session_key . '_sorting', $dataProvider->sort->getAttributeOrders() );
		}

		return $dataProvider;
	}


	// Extendable functions used in actions
	// --------------------------------------------------

	/**
	 * Return a data provider for index action
	 *
	 * @param ActiveRecord $searchModel
	 *
	 * @return mixed
	 * @throws yii\base\InvalidConfigException
	 */
	protected function indexDataProvider( ActiveRecord $searchModel ) {
		return $this->setupDataProvider(
			$searchModel,
			'',
			[],
			$this->persist_grid_filters,
			$this->persist_grid_page,
			$this->persist_grid_order
		);
	}

	/**
	 * Provides array of data to be send with 'index' action/view
	 *
	 * @param ActiveRecord $searchModel
	 * @param $dataProvider
	 *
	 * @return array
	 * @throws yii\base\InvalidConfigException
	 */
	protected function indexRenderData( ActiveRecord $searchModel, $dataProvider ) {
		return [
			'searchModel'  => $searchModel,
			'dataProvider' => $dataProvider,
			'model_id'     => $this->getModelId()
		];
	}

	/**
	 * Provides array of data to be send with 'view' action/view
	 *
	 * @return array
	 * @throws yii\base\InvalidConfigException
	 */
	protected function viewRenderData() {
		return [
			'model'    => $this->model,
			'model_id' => $this->getModelId(),
		];
	}

	/**
	 * Builds modal footer when record is viewed
	 *
	 * @return string html
	 */
	protected function viewModalFooter() {
		return Html::button( yii::t( 'radtools', 'Close' ), [
				'class'        => 'btn btn-default pull-left',
				'data-dismiss' => 'modal',
			] ) .
		       ( ! $this->useDetailView ?
			       Html::a(
			       	yii::t( 'yii', 'Delete' ),
			        [ 'delete', 'id' => $this->model->{$this->model_id_field} ],
			        [
				       'class' => 'btn btn-danger',
				       'role'  => 'modal-remote',
				       'data'  => [
					       'confirm' => Yii::t( 'yii', 'Are you sure you want to delete this item?' ),
					       'method'  => 'post',
				       ],
			       ] ) .
			       Html::a(
			       	yii::t( 'radtools', 'Edit' ),
			        [ 'update', 'id' => $this->model->{$this->model_id_field} ],
			        [
				       'class' => 'btn btn-primary',
				       'role'  => 'modal-remote',
			       ] ) :
			       '' );
	}

	/**
	 * Provides array of data to be send with 'create' action/view
	 *
	 * @return array
	 * @throws yii\base\InvalidConfigException
	 */
	protected function createRenderData() {
		return [
			'model'    => $this->model,
			'model_id' => $this->getModelId(),
		];
	}

	/**
	 * Builds modal footer when record is created
	 *
	 * @return string html
	 */
	protected function createModalFooterEdit() {
		return Html::button(
				yii::t( 'radtools', 'Close' ),
				[
					'class'        => 'btn btn-default pull-left',
					'data-dismiss' => 'modal',
				] ) .
		       Html::button(
			       yii::t( 'radtools', 'Create' ),
			       [ 'class' => 'btn btn-primary', 'type' => 'submit' ]
		       );
	}

	/**
	 * Builds modal footer when created record is saved
	 *
	 * @return string html
	 */
	protected function createModalFooterSaved() {
		return Html::button(
				yii::t( 'radtools', 'Close' ),
				[
					'class'        => 'btn btn-default pull-left',
					'data-dismiss' => 'modal',
				] ) .
		       Html::a(
			       yii::t( 'radtools', 'Edit' ),
			       [ 'update', 'id' => $this->model->{$this->model_id_field} ],
			       [
				       'class' => 'btn btn-primary',
				       'role'  => 'modal-remote',
			       ]
		       );
	}

	/**
	 * Provides array of data to be send with 'update' action/view
	 *
	 * @return array
	 * @throws yii\base\InvalidConfigException
	 */
	protected function updateRenderData() {
		return [
			'model'    => $this->model,
			'model_id' => $this->getModelId(),
		];
	}

	/**
	 * Builds modal footer when record is updated
	 *
	 * @return string html
	 */
	protected function updateModalFooterEdit() {
		return Html::button(
				yii::t( 'radtools', 'Close' ),
				[
					'class'        => 'btn btn-default pull-left',
					'data-dismiss' => 'modal',
				]
			) .
		       Html::button(
			       yii::t( 'radtools', 'Update' ),
			       [ 'class' => 'btn btn-primary', 'type' => 'submit' ]
		       );
	}

	/**
	 * Builds modal footer after record is saved
	 *
	 * @return string html
	 */
	protected function updateModalFooterSaved() {
		return Html::button( yii::t( 'radtools', 'Close' ), [
				'class'        => 'btn btn-default pull-left',
				'data-dismiss' => 'modal',
			] ) .
		       ( ! $this->useDetailView ?
			       Html::a(
				       yii::t( 'yii', 'Delete' ),
				       [ 'delete', 'id' => $this->model->{$this->model_id_field} ],
				       [
					       'class' => 'btn btn-danger',
					       'role'  => 'modal-remote',
					       'data'  => [
						       'confirm' => Yii::t( 'yii', 'Are you sure you want to delete this item?' ),
						       'method'  => 'post',
					       ],
				       ] ) .
			       Html::a(
				       yii::t( 'yii', 'Update' ),
				       [ 'update', 'id' => $this->model->{$this->model_id_field} ],
				       [
					       'class' => 'btn btn-primary',
					       'role'  => 'modal-remote',
				       ] ) :
			       '' );
	}

	/**
	 * Creates a redirect code with return_url and parameters combined
	 *
	 * @param string|array $return_url
	 * - a string representing a URL (e.g. "http://example.com")
	 * - a string representing a URL alias (e.g. "@example.com")
	 * - an array in the format of `[$route, ...name-value pairs...]` (e.g. `['site/index', 'ref' => 1]`)
	 *   [[Url::to()]] will be used to convert the array into a URL.
	 *
	 * Any relative URL that starts with a single forward slash "/" will be converted
	 * into an absolute one by prepending it with the host info of the current request.
	 *
	 * @param array $base_get_params
	 *
	 * @return Response
	 */
	protected function redirectReturnUrl( $return_url, array $base_get_params = [] ) {
		// Handle
		if ( is_array( $return_url ) ) {
			return $this->redirect(
				[ array_shift( $return_url ) ] + ArrayHelper::merge( $base_get_params, $return_url )
			);
		}

		// Decode the return URL
		$return_url = urldecode( $return_url );

		return $this->redirect(
			Url::urlQueryMerge( $return_url,  $base_get_params )
		);
	}

	/**
	 * Response for crud actions
	 *
	 * @param string $view
	 * @param array $render_data
	 * @param string $modal_title
	 * @param string $modal_footer
	 *
	 * @return array|string|Response
	 */
	protected function crudActionResponse(
		string $view,
		array $render_data,
		string $modal_title,
		string $modal_footer
	) {
		// Ajax Crud modal request
		if ( yii::$app->request->isAjax && ! yii::$app->request->isPjax ) {
			// Ajax request
			yii::$app->response->format = Response::FORMAT_JSON;

			// Start (or fail) show form
			return [
				'title'   => $modal_title,
				'content' => $this->renderAjax( $view, $render_data ),
				'footer'  => $modal_footer,
			];
		}

		//  Default page based request

		// Start (or fail) show form
		return $this->render( $view, $render_data );
	}

	/**
	 * Success response for crud actions
	 *
	 * @param array $render_data
	 * @param string $modal_footer
	 * @param string $message
	 * @param string|array $default_return_url
	 *
	 * @return array|string|Response
	 * @throws yii\base\InvalidConfigException
	 */
	protected function crudActionSuccessResponse(
		array $render_data,
		string $modal_footer,
		string $message,
		$default_return_url
	) {
		// Determine return_url
		$return_url = yii::$app->request->get( 'return_url', $default_return_url );

		// Ajax Crud modal request
		if ( yii::$app->request->isAjax && ! yii::$app->request->isPjax ) {
			// Ajax request
			yii::$app->response->format = Response::FORMAT_JSON;

			$return_view = is_array( $return_url ) ? $return_url[0] : $return_url;

			if ( $return_view == 'index' ) {
				$result = [
					'forceReload' => $this->getGridviewPjaxId(),
					'forceClose'  => true,
				];
			} elseif ( in_array( $return_view, [ 'view', 'update' ] ) ) {
				// Success
				$result = [
					'forceReload' => $this->getGridviewPjaxId(),
					'title'       => $this->view->title,
					'content'     => '<div class="text-success">' . $message . '</div>' .
					                 $this->renderAjax(
						                 $return_view,
						                 $render_data
					                 ),
					'footer'      => $modal_footer,
				];
			} else {
				$result = [
					'forceRedirect' => Url::to( $return_url ),
				];
			}

			return $this->afterCrudSuccess(
				$this->action->id,
				$this->model,
				$result
			);
		}

		//  Default page based request

		// Success, go back to return_url
		yii::$app->session->setFlash( 'alert', [
			'body'    => $message,
			'options' => [ 'class' => 'alert-success' ],
		] );

		return $this->afterCrudSuccess(
			$this->action->id,
			$this->model,
			$this->redirectReturnUrl( $return_url, [ 'id' => $this->model->{$this->model_id_field} ] )
		);
	}

	/**
	 * Build response after bulk action
	 *
	 * @param string $title
	 * @param string $message
	 * @param array|string $default_return_url Optional, default 'return_url' get parameter is used
	 * @param array $errors Optional, record_id => error message array
	 *
	 * @return array|string|Response
	 * @throws yii\base\InvalidConfigException
	 */
	protected function bulkActionResponse(
		string $title,
		string $message,
		$default_return_url = [ 'index' ],
		array $errors = []
	) {
		// Determine return_url
		$return_url = yii::$app->request->get('return_url', $default_return_url);

		// Parse errors in a string
		$error_string = $errors ?
			$title .
			Html::ul( $errors, [ 'encode' => false, ] ) :
			null;

		if ( yii::$app->request->isAjax && ! yii::$app->request->isPjax ) {
			// Ajax request
			yii::$app->response->format = Response::FORMAT_JSON;

			$return_view = is_array( $return_url ) ? $return_url[0] : $return_url;

			if ( $return_view == 'index' ) {
				// Back to grid: reload grid and close modal
				return [
					'forceReload' => $this->getGridviewPjaxId(),
					'forceClose'  => true,
					'title'       => $title,
					'message'     => $message . '<br/>' . $error_string,
					'footer'      => Html::button( 'Close', [
						'class'        => 'btn btn-default pull-left',
						'data-dismiss' => 'modal',
					] ),
				];
			} else {
				// Prepare error and message flashes
				if ( $message ) {
					yii::$app->session->setFlash( 'alert', [
						'body'    => $message,
						'options' => [ 'class' => 'alert-success' ],
					] );
				}

				if ( $error_string ) {
					yii::$app->session->setFlash( 'alert', [
						'body'    => $error_string,
						'options' => [ 'class' => 'alert-danger' ],
					] );
				}

				// A different return URL: redirect to this URL
				return [
					'forceRedirect' => Url::to( $return_url ),
				];
			}
		}

		// Prepare error and message flashes
		if ( $message ) {
			yii::$app->session->setFlash( 'alert', [
				'body'    => $message,
				'options' => [ 'class' => 'alert-success' ],
			] );
		}

		if ( $error_string ) {
			yii::$app->session->setFlash( 'alert', [
				'body'    => $error_string,
				'options' => [ 'class' => 'alert-danger' ],
			] );
		}

		// Non-ajax request
		return $this->redirectReturnUrl( $return_url );
	}


	// Extendable render data functions for actions
	// --------------------------------------------------

	/**
	 * Gridview Pjax div id
	 *
	 * @return string
	 * @throws yii\base\InvalidConfigException
	 */
	protected function getGridviewPjaxId() {
		return '#' . $this->getModelId() .
		       // yii2-ajaxcrud uses datatable
		       ( $this->useDynagrid ? '-gridview' : '-datatable' ) .
		       '-pjax';
	}

	/**
	 * Adds a button which takes the user out of modal to a full page view for given controller action
	 *
	 * @param string $action
	 *
	 * @return string
	 */
	protected function modalToFullpageLink( string $action ) {
		return $this->viewShowFullpageLink ?
			Html::a(
				'<span class="glyphicon glyphicon-fullscreen"></span>',
				yii\helpers\Url::to(
					[ $action, 'id' => $this->model->{$this->model_id_field} ]
				)
			) . '&nbsp;' :
			'';
	}


	// Model record creation and retrieval
	// --------------------------------------------------

	/**
	 * Create a new model record
	 * @return mixed
	 */
	protected function newModel() {
		$this->model = new $this->modelClass();
		return $this->model;
	}

	/**
	 * Finds the model based on its primary key value.
	 * If the model is not found, a 404 HTTP exception will be thrown.
	 *
	 * @param integer $id
	 * @param bool $throw_not_found
	 *
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	protected function findModel( int $id, bool $throw_not_found = true ) {
		$modelClass = $this->modelClass;
		$this->model = $modelClass::findOne( $id );
		if ( ! $this->model && $throw_not_found ) {
			throw new NotFoundHttpException( 'The requested page does not exist.' );
		}
	}
}