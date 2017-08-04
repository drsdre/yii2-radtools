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
use yii\base\Exception;
use yii\web\NotFoundHttpException;
use yii\helpers\Html;
use yii\web\Response;
use yii\helpers\ArrayHelper;
use drsdre\radtools\helpers\Url;

/**
 * Implements Ajax CRUD actions for a model.
 */
class RadCrudController extends Controller {

	/** @var ActiveRecord $model current selected record for CRUD operations */
	protected $model;

	/** @var ActiveRecord $modelClass class name of CRUD record with namespace [required] */
	protected $modelClass;

	/** @var ActiveRecord $searchModelClass class name of ActiveRecord used for searching [required] */
	protected $searchModelClass;

	/** @var string $model_name displayed type name of record [required] */
	protected $model_name;

	/** @var string $model_field_name ActiveRecord field name used to display name of record [required] */
	protected $model_field_name;

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
	protected $copySuccessRedirect = [ 'update' ];

	/** @var array|string $copySuccessRedirect default url to redirect to when copy was successful */
	protected $deleteSuccessRedirect = [ 'index' ];

	/** @var array|string $bulkDeleteSuccessRedirect default url to redirect to when bulk delete was successful */
	protected $bulkDeleteSuccessRedirect = [ 'index' ];

	/** @var string $bulkUpdateSuccessRedirect default url to redirect to when bulk update was successful */
	protected $bulkUpdateSuccessRedirect = [ 'index' ];

	/** @var string $viewShowFullpageLink when true, adds a clickable icon in modal view to switch to full page view */
	protected $viewShowFullpageLink = false;

	/**
	 * @event AfterCrudEvent an event that is triggered after a crud action is completed.
	 */
	const EVENT_AFTER_CRUD_SUCCESS = 'afterCrudSuccess';

	// Actions
	// ----------------------------------

	/**
	 * Lists all records (for gridview)
	 *
	 * @return string
	 */
	public function actionIndex() {

		// Setup page title and first breadcrumb
		$this->view->title = yii::t( 'app', '{object} Overview', [
			'object' => $this->model_name,
		] );
		$this->addBreadCrumbs( [ $this->view->title ] );

		// Setup data feed
		$searchModel  = new $this->searchModelClass();
		$dataProvider = $this->indexDataProvider( $searchModel );

		return $this->render( 'index', $this->indexRenderData( $searchModel, $dataProvider ) );
	}

	/**
	 * View model record.
	 *
	 * @param integer $id
	 *
	 * @return string
	 */
	public function actionView( int $id ) {
		$request = yii::$app->request;

		$this->findModel( $id );

		// Setup page title and first breadcrumb
		$this->view->title = yii::t( 'app', 'View {model_object_name}',
			[ 'model_object_name' => $this->getModelObjectName() ]
		);
		$this->addBreadCrumbs( [
			[ 'label' => $this->model_name . ' Overview', 'url' => [ 'index' ] ],
			ArrayHelper::getValue( $this->model, $this->model_field_name ),
		] );

		// Updates from DetailView
		if ( $this->useDetailView ) {

			// Set a model scenario if specified
			if ( isset( $this->model_update_scenario ) ) {
				$this->model->setScenario( $this->model_update_scenario );
			}

			// Load the data
			if ( $this->model->load( $request->post() ) ) {
				// Save the data
				$session = yii::$app->session;
				if ( $this->model->save() ) {
					// Save success
					$session->setFlash( 'kv-detail-success', yii::t( 'app', 'Saved successfully' ) );
					return $this->afterCrudSuccess(
						$this->action->id,
						$this->model,
						$this->redirect( [ 'view', 'id' => $this->model->id ] )
					);
				} else {
					// Flash validation error(s)
					$session->setFlash( 'kv-detail-error', yii::t( 'app', 'Error(s) saving' ) );
				}
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
	 * @return array|string|Response
	 */
	public function actionCreate() {
		$request = yii::$app->request;

		// Setup a new record
		$this->model = $this->newModel();

		// Setup page title and first breadcrumb
		$this->view->title = yii::t( 'app', 'Create new {model_name}', [
			'model_name' => $this->model_name,
		] );
		$this->addBreadCrumbs( [
			[ 'label' => $this->model_name . ' Overview', 'url' => [ 'index' ] ],
			yii::t( 'app', 'Create' ),
		] );

		// Set a model scenario if specified
		if ( isset( $this->model_create_scenario ) ) {
			$this->model->setScenario( $this->model_create_scenario );
		}

		// Load, validate and save model data
		if ( ! $request->isGet && $this->model->load( $request->post() ) && $this->model->save() ) {
			// Success
			return $this->crudActionSuccessResponse(
				$this->updateRenderData(),
				$this->createModalFooterSaved(),
				yii::t( 'app', '{model_name} {model_object_name} created', [
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
	 * @return string
	 */
	public function actionCopy( int $id ) {
		$request = yii::$app->request;

		$this->findModel( $id );

		// Mark record as new
		$this->model->id          = null;
		$this->model->isNewRecord = true;

		// Setup page title and first breadcrumb
		$this->view->title = yii::t( 'app', 'Copy {model_object_name}',
			[ 'model_object_name' => $this->getModelObjectName() ]
		);
		$this->addBreadCrumbs( [
			[ 'label' => $this->model_name . ' Overview', 'url' => [ 'index' ] ],
			yii::t( 'app', 'Copy' ),
		] );

		// Set a model scenario if specified
		if ( isset( $this->model_create_scenario ) ) {
			$this->model->setScenario( $this->model_create_scenario );
		}

		// Load, validate and save model data
		if ( ! $request->isGet && $this->model->load( $request->post() ) && $this->model->save() ) {
			// Success
			return $this->crudActionSuccessResponse(
				$this->updateRenderData(),
				$this->createModalFooterSaved(),
				yii::t( 'app', '{model_name} {model_object_name} copied from {id}', [
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
	 * @return string
	 */
	public function actionUpdate( int $id ) {
		$request = yii::$app->request;

		$this->findModel( $id );

		// Setup generic view settings
		$this->view->title = yii::t( 'app', 'Update {model_object_name}', [
			'model_object_name' => $this->getModelObjectName(),
		] );
		$this->addBreadCrumbs( [
			[ 'label' => $this->model_name . ' Overview', 'url' => [ 'index' ] ],
			[
				'label' => ArrayHelper::getValue( $this->model, $this->model_field_name ),
				'url'   => [ 'view', 'id' => $this->model->id ],
			],
			yii::t( 'app', 'Update' ),
		] );

		// Set a model scenario if specified
		if ( isset( $this->model_update_scenario ) ) {
			$this->model->setScenario( $this->model_update_scenario );
		}

		// Load, validate and save model data
		if ( ! $request->isGet && $this->model->load( $request->post() ) && $this->model->save() ) {
			// Success
			return $this->crudActionSuccessResponse(
				$this->updateRenderData(),
				$this->updateModalFooterSaved(),
				yii::t( 'app', '{model_name} {model_object_name} updated', [
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
	 * @return string|array
	 */
	public function actionDelete( int $id = null ) {
		$request = yii::$app->request;

		// If detail view, try to get id from custom_param
		if ($this->useDetailView ) {
			$id = $request->post('id', $id);
		}

		$this->findModel( $id );

		// Setup URL to return after success
		$return_url = $request->get( 'return_url', $this->deleteSuccessRedirect );

		// Determine if request is from modal
		$modal_request = $request->isAjax && ! $request->isPjax;
		$ajax_response = null;

		// Delete the record
		$delete_result = $this->deleteModel();

		// Check for error message
		$error_msg = false;
		$succes_msg = '';
		if ( is_string( $delete_result ) ) {
			$error_msg = yii::t( 'app', 'Cannot delete {model_object_name}: {error}',
				[
					'model_object_name' => $this->getModelObjectName(),
					'error'             => $delete_result,
				]
			);
			// Check for result false
		} elseif ( $delete_result === false ) {
			$error_msg = yii::t( 'app', 'Cannot delete {model_object_name}',
				[ 'model_object_name' => $this->getModelObjectName() ]
			);
		} else {
			$succes_msg = yii::t( 'app', '{model_object_name} deleted',
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
								                    yii::t( 'app', 'Return' ),
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
					'title'   => yii::t( 'app', 'Delete failed' ),
					'content' => $error_msg,
					'footer'  => Html::button( 'Close', [
						'class'        => 'btn btn-default pull-left',
						'data-dismiss' => 'modal',
					] ),
				];
			} else {
				if ( $return_url == 'index' ) {
					// Back to grid: reload grid and close modal
					$ajax_response = [
						'forceReload' => $this->getGridviewPjaxId(),
						'forceClose'  => true,
					];
				} else {
					// A different return URL: redirect to this URL
					$ajax_response = [
						'forceRedirect' => $return_url,
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
	 * @return string
	 */
	public function actionBulkDelete() {
		$request = yii::$app->request;

		$errors = [];
		$record_count = 0;

		// For all given id's (pks)
		$pks = explode( ',', $request->post( 'pks' ) );
		foreach ( $pks as $pk ) {
			// Get the model and delete
			$this->findModel( $pk, false );

			// Check if model is found
			if ( ! $this->model ) {
				$errors[ $pk ] = yii::t( 'app', 'Record not found' );
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
								yii::t( 'app', 'Delete \'{object}\' error:', [
									'object' => ArrayHelper::getValue( $this->model, $this->model_field_name )
								] )
						]
					);
					continue;
				}

					// Check for delete_result message
				elseif ( is_string( $delete_result ) ) {
					$errors[ $pk ] = yii::t( 'app', 'Cannot delete {model_object_name}: {result}', [
						'model_object_name' => $this->getModelObjectName(),
						'result' => $delete_result,
					] );

					// General error
				} else {
					$errors[ $pk ] = yii::t( 'app', 'Cannot delete {model_object_name}: reason unknown', [
						'model_object_name' => $this->getModelObjectName(),
					] );
				}
			}

			$record_count ++;
		}


		return $this->bulkActionResponse(
			yii::t( 'app', 'Bulk Delete' ),
			yii::t( 'app', '{record_count,plural,=0{No} =1{One} other{#}} {mobel_name} deleted',
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
	 * @return Response|array
	 *
	 * @throws NotFoundHttpException
	 */
	public function actionBulkUpdate() {
		$request = yii::$app->request;

		// Parse the fields to update
		$update_attribute_value = [];
		$model                  = new $this->modelClass;
		/** @var ActiveRecord $model */
		foreach ( $model->activeAttributes() as $attribute ) {
			// Check if a value is provided
			if ( ! is_null( $request->post( $attribute ) ) ) {
				$update_attribute_value[ $attribute ] = $request->post( $attribute );
			}
		}

		$errors = [];
		$records_updated = 0;

		// CHeck if there are fields to update
		if ( ! $update_attribute_value ) {
			$errors[] = yii::t( 'app', 'No fields found to update.' );
		} else {
			// Update the fields for all the selected records (pks)
			$pks = explode( ',', $request->post( 'pks' ) );
			foreach ( $pks as $id ) {
				$this->findModel( (int) $id, false );

				// Check if model is found
				if ( ! $this->model ) {
					$errors[ $id ] = yii::t( 'app', 'Record {id} not found', [ 'id' => $id ] );
					continue;
				}

				// Set a model scenario if specified
				if ( isset( $this->model_update_scenario ) ) {
					$this->model->setScenario( $this->model_update_scenario );
				}

				// Update the variables
				$this->model->attributes = $update_attribute_value;

				// Check if record is changed
				if ( $this->model->getDirtyAttributes() ) {
					// Save data
					if ( ! $this->model->save() ) {
						// Track errors
						$errors[ $id ] = Html::errorSummary(
							$this->model,
							[
								'header' =>
									yii::t( 'app', 'Update \'{object}\' error:', [
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
			yii::t( 'app', 'Bulk Update' ),
			yii::t( 'app', '{record_count,plural,=0{no records} =1{one record} other{# records}} updated', [
				'record_count' => $records_updated,
			] ),
			$this->bulkUpdateSuccessRedirect,
			$errors
		);
	}

	// Public non-action functions
	// --------------------------------------------------

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
		$event = new AfterCrudEvent($action);
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
	public function deleteModel() {
		try {
			return $this->model->delete();
		} catch ( \Exception $e ) {
			// Build error message
			if ( $e->getCode() === 23000 && preg_match( '/CONSTRAINT `(.*)` FOREIGN/', $e->getMessage(),
					$matches ) === 1
			) {
				// Handle SQL foreign key errors
				return yii::t( 'app', 'The record is linked with {foreign_key}. Unlink before delete.',
					[ 'foreign_key' => $matches[1] ] );
			} else {
				return $e->getMessage();
			}
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
		return self::$persist_grid_session_key . '_' . $this->className();
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
	 */
	public function setupDataProvider(
		ActiveRecord $searchModel,
		string $grid_id = '',
		array $base_where_filter = [],
		bool $persist_filters = false,
		bool $persist_page = false,
		bool $persist_order = false
	) {
		$request = yii::$app->request;
		$session = yii::$app->session;

		// Setup persistence parameters
		$session_key      = $this->dataProviderSessionKey() . $grid_id;
		$persistent_reset = $request->get( $grid_id . self::$grid_persistent_reset_param, false );

		if ( $persist_filters ) {
			// Setup persistent filtering
			if ( $persistent_reset ) {
				// Clear query filters on filter reset
				$session->remove( $session_key . '_filters' );

				Yii::trace( 'Persistent filters reset', __METHOD__ );
			} elseif ( ! $request->get( $searchModel->formName(), false ) ) {
				// If no filters set in query, load persisted filters from session into search model
				$searchModel->setAttributes( $session->get( $session_key . '_filters', [] ) );

				Yii::trace( 'Persistent filters read from session', __METHOD__ );
			} else {
				// If filtering changed, remove page persistence
				$session->remove( $session_key . '_page' );

				Yii::trace( 'Persistent filters changed, clear persistent page', __METHOD__ );
			}
		}

		// Create dataProvider
		$dataProvider = $searchModel->search( $request->queryParams );

		// Persist query filters from search
		if ( $persist_filters ) {
			$session->set( $session_key . '_filters', $searchModel->getAttributes() );
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
				$session->remove( $session_key . '_page' );

				Yii::trace( 'Persistent page reset', __METHOD__ );
			}

			// Get page number from query
			$page_number = $request->get( $dataProvider->pagination->pageParam, false );

			// If page_number is not in query, use persisted page selection
			if ( $page_number === false ) {
				$page_number = $session->get( $session_key . '_page', 0 );
				if ( $page_number <= $dataProvider->pagination->pageCount ) {
					$dataProvider->pagination->page = $page_number;

					Yii::trace( 'Persistent page ' . $page_number . ' read from session', __METHOD__ );
				}
			}

			// Set page number and persist it
			$session->set( $session_key . '_page', $page_number );
		}

		// Persistent sorting
		if ( $persist_order ) {
			if ( $persistent_reset ) {
				// Reset parameter
				$session->remove( $session_key . '_sorting' );

				Yii::trace( 'Persistent order reset', __METHOD__ );
			}

			// If no current order, use persisted order
			if ( ! $dataProvider->sort->getAttributeOrders() ) {
				$dataProvider->sort->setAttributeOrders( $session->get( $session_key . '_sorting', [] ) );

				Yii::trace( 'Persistent order read from session', __METHOD__ );
			}

			// Persist the current ordering
			$session->set( $session_key . '_sorting', $dataProvider->sort->getAttributeOrders() );
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
		return Html::button( yii::t( 'app', 'Close' ), [
				'class'        => 'btn btn-default pull-left',
				'data-dismiss' => 'modal',
			] ) .
		       ( ! $this->useDetailView ?
			       Html::a( yii::t( 'app', 'Delete' ), [ 'delete', 'id' => $this->model->id ], [
				       'class' => 'btn btn-danger',
				       'role'  => 'modal-remote',
				       'data'  => [
					       'confirm' => Yii::t( 'app', 'Are you sure you want to delete this item?' ),
					       'method'  => 'post',
				       ],
			       ] ) .
			       Html::a( yii::t( 'app', 'Edit' ), [ 'update', 'id' => $this->model->id ], [
				       'class' => 'btn btn-primary',
				       'role'  => 'modal-remote',
			       ] ) :
			       '' );
	}

	/**
	 * Provides array of data to be send with 'create' action/view
	 *
	 * @return array
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
		return Html::button( yii::t('app', 'Close'), [
				'class'        => 'btn btn-default pull-left',
				'data-dismiss' => 'modal',
			] ) .
		       Html::button( yii::t('app', 'Create'), [ 'class' => 'btn btn-primary', 'type' => 'submit' ] );
	}

	/**
	 * Builds modal footer when created record is saved
	 *
	 * @return string html
	 */
	protected function createModalFooterSaved() {
		return Html::button( yii::t('app', 'Close'), [
				'class'        => 'btn btn-default pull-left',
				'data-dismiss' => 'modal',
			] ) .
		       Html::a( yii::t('app', 'Edit'), [ 'update', 'id' => $this->model->id ], [
			       'class' => 'btn btn-primary',
			       'role'  => 'modal-remote',
		       ] );
	}

	/**
	 * Provides array of data to be send with 'update' action/view
	 *
	 * @return array
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
		return Html::button( yii::t('app', 'Close'), [
				'class'        => 'btn btn-default pull-left',
				'data-dismiss' => 'modal',
			] ) .
		       Html::button( yii::t('app', 'Update'), [ 'class' => 'btn btn-primary', 'type' => 'submit' ] );
	}

	/**
	 * Builds modal footer after record is saved
	 *
	 * @return string html
	 */
	protected function updateModalFooterSaved() {
		return Html::button( yii::t('app', 'Close'), [
				'class'        => 'btn btn-default pull-left',
				'data-dismiss' => 'modal',
			] ) .
		       ( ! $this->useDetailView ?
			       Html::a( yii::t( 'app', 'Delete' ), [ 'delete', 'id' => $this->model->id ], [
				       'class' => 'btn btn-danger',
				       'role'  => 'modal-remote',
				       'data'  => [
					       'confirm' => Yii::t( 'app', 'Are you sure you want to delete this item?' ),
					       'method'  => 'post',
				       ],
			       ] ) .
			       Html::a( yii::t( 'app', 'Edit' ), [ 'update', 'id' => $this->model->id ], [
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
			return $this->redirect( [
				array_shift( $return_url ),
				ArrayHelper::merge( $base_get_params, $return_url ),
			] );
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
		$request = yii::$app->request;

		// Ajax Crud modal request
		if ( $request->isAjax && ! $request->isPjax ) {
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
	 */
	protected function crudActionSuccessResponse(
		array $render_data,
		string $modal_footer,
		string $message,
		$default_return_url
	) {
		$request = yii::$app->request;

		// Determine return_url
		$return_url = $request->get('return_url', $default_return_url);

		// Ajax Crud modal request
		if ( $request->isAjax && ! $request->isPjax ) {
			// Ajax request
			yii::$app->response->format = Response::FORMAT_JSON;

			if ( $return_url == 'index') {
				$result = [
					'forceReload' => $this->getGridviewPjaxId(),
					'forceClose'  => true,
				];
			} elseif ( in_array( $return_url, ['view', 'update'] ) ) {
				// Success
				$result = [
					'forceReload' => $this->getGridviewPjaxId(),
					'title'       => $this->view->title,
					'content'     => '<div class="text-success">' . $message . '</div>'.
					                 $this->renderAjax(
						                 $return_url,
						                 $render_data
					                 ),
					'footer'      => $modal_footer,
				];
			} else {
				$result = [
					'forceRedirect' => $return_url,
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
			$this->redirectReturnUrl( $return_url, [ 'id' => $this->model->id ] )
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
	 */
	protected function bulkActionResponse(
		string $title,
		string $message,
		$default_return_url = [ 'index' ],
		array $errors = []
	) {
		$request = yii::$app->request;
		$response = yii::$app->response;
		$session = yii::$app->session;

		// Determine return_url
		$return_url = $request->get('return_url', $default_return_url);

		// Parse errors in a string
		$error_string = $errors ?
			$title .
			Html::ul( $errors, [ 'encode' => false, ] ) :
			null;

		if ( $request->isAjax && ! $request->isPjax ) {
			// Ajax request
			$response->format = Response::FORMAT_JSON;

			if ( $return_url == 'index' ) {
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
					$session->setFlash( 'alert', [
						'body'    => $message,
						'options' => [ 'class' => 'alert-success' ],
					] );
				}

				if ( $error_string ) {
					$session->setFlash( 'alert', [
						'body'    => $error_string,
						'options' => [ 'class' => 'alert-danger' ],
					] );
				}

				// A different return URL: redirect to this URL
				return [
					'forceRedirect' => $return_url,
				];
			}
		}

		// Prepare error and message flashes
		if ( $message ) {
			$session->setFlash( 'alert', [
				'body'    => $message,
				'options' => [ 'class' => 'alert-success' ],
			] );
		}

		if ( $error_string ) {
			$session->setFlash( 'alert', [
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
			Html::a( '<span class="glyphicon glyphicon-fullscreen"></span>',
				yii\helpers\Url::to(
					[ $action, 'id' => $this->model->id ]
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
		return new $this->modelClass();
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