<?php
/**
 * Base Ajax Crud Controller
 * Should be extended from your frontend controller for Yii2-ajaxcrud based crud UI components.
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
use yii\helpers\Json;
use yii\helpers\ArrayHelper;

/**
 * Implements Ajax CRUD actions for a model.
 */
class BaseAjaxCrudController extends Controller {

	/** @var bool $useDynagrid set to true if Kartik-v DynaGrid is being used */
	protected $useDynagrid = false;

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

	/** @var int $persist_grid_expiration expiration in seconds to forget persistent grid parameter after last usage */
	protected $persist_grid_expiration = 7200;

	/** @var bool $persist_grid_filters to persist grid filter or not */
	protected $persist_grid_filters = false;

	/** @var bool $persist_grid_page to persist grid page or not */
	protected $persist_grid_page = false;

	/** @var bool $persist_grid_order to persist grid order or not */
	protected $persist_grid_order = false;

	/** @var ActiveRecord $model current selected record for CRUD operations */
	protected $model;

	/** @var string $updateSuccessRedirect view to redirect to when update was successful */
	protected $updateSuccessRedirect = 'index';

	/** @var string $createSuccessRedirect default url to redirect to when create was successful */
	protected $createSuccessRedirect = 'index';

	/** @var string $copySuccessRedirect default url to redirect to when copy was successful */
	protected $copySuccessRedirect = 'update';

	/** @var string $copySuccessRedirect default url to redirect to when copy was successful */
	protected $deleteSuccessRedirect = 'index';

	/** @var string $bulkDeleteSuccessRedirect default url to redirect to when bulk delete was successful */
	protected $bulkDeleteSuccessRedirect = 'index';

	/** @var string $bulkUpdateSuccessRedirect default url to redirect to when bulk update was successful */
	protected $bulkUpdateSuccessRedirect = 'index';

	/** @var string $viewShowFullpageLink when true, adds a clickable icon in modal view to switch to full page view */
	protected $viewShowFullpageLink = false;

	/**
	 * ID for pjax forceUpdate
	 *
	 * @return string
	 */
	protected function pjaxForceUpdateId() {
		if ( $this->useDynagrid ) {
			$searchModel = new $this->searchModelClass();

			return '#' . $searchModel::tableName() . '-gridview-pjax';
		} else {
			return '#crud-datatable-pjax';
		}
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
	 * Add breadcrumbs to the view
	 *
	 * @param array $crumbs
	 */
	public function addBreadCrumbs( $crumbs ) {
		foreach ( $crumbs as $crumb ) {
			$this->view->params['breadcrumbs'][] = $crumb;
		}
	}

	/**
	 * Output parsing for gridview editable calls
	 *
	 * @param array $posted
	 * @param activeRecord $model
	 *
	 * @return string
	 */
	protected function indexEditableOutput( array $posted, $model ) {
		// custom output to return to be displayed as the editable grid cell
		// data. Normally this is empty - whereby whatever value is edited by
		// in the input by user is updated automatically.

		// Specific use case where you need to validate a specific
		// editable column posted when you have more than one
		// EditableColumn in the grid view.
		return '';
	}

	/**
	 * Provides array of data to be send to render index
	 *
	 * @param $searchModel
	 * @param $dataProvider
	 *
	 * @return array
	 */
	protected function indexRenderData( ActiveRecord $searchModel, $dataProvider ) {
		return [
			'searchModel'  => $searchModel,
			'dataProvider' => $dataProvider,
		];
	}

	/**
	 * Provides array of data to be send to view
	 *
	 * @return array
	 */
	protected function viewRenderData() {
		return [
			'model' => $this->model,
		];
	}

	/**
	 * Provides array of data to be send to render create
	 *
	 * @return array
	 */
	protected function createRenderData() {
		return [
			'model' => $this->model,
		];
	}

	/**
	 * Provides array of data to be send to render update
	 *
	 * @return string
	 */
	protected function updateRenderData() {
		return [
			'model' => $this->model,
		];
	}

	/**
	 * Get data provider for index
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
	 * Setup a DataProvider with optional filter/page/order persistence
	 *
	 * @param ActiveRecord $searchModel
	 * @param string $grid_id           when multiple dataProvider widgets are used on a page
	 * @param array $base_where_filter  base filter which is applied with ActiveQuery andWhere after search
	 * @param bool $persist_filters
	 * @param bool $persist_page
	 * @param bool $persist_order
	 *
	 * @return mixed
	 */
	public function setupDataProvider(
		ActiveRecord $searchModel,
		$grid_id = '',
		array $base_where_filter = [],
		$persist_filters = false,
		$persist_page = false,
		$persist_order = false
	) {
		$request = Yii::$app->request;
		$session = Yii::$app->session;

		// Setup persistence parameters
		$session_key      = $this->dataProviderSessionKey() . $grid_id;
		$persistent_reset = $request->get( $grid_id . self::$grid_persistent_reset_param, false );

		if ( $persist_filters ) {
			// Setup persistent filtering
			if ( $persistent_reset ) {
				// Clear query filters on filter reset
				$session->remove( $session_key . '_filters');
			} elseif ( ! $request->get( $searchModel->formName(), false ) ) {
				// If no filters set in query, load persisted filters from session into search model
				$searchModel->setAttributes( $session->get( $session_key . '_filters', [] ) );
			} else {
				// If filtering changed, remove page persistence
				$session->remove( $session_key . '_page' );
			}
		}

		// Create dataProvider
		$dataProvider = $searchModel->search( $request->queryParams );

		// Persist query filters from search
		if ( $persist_filters ) {
			$session->set( $session_key . '_filters', $searchModel->getAttributes(), $this->persist_grid_expiration );
		}

		// Apply default filters if provided
		if (count($base_where_filter)) {
			$dataProvider->query->andWhere($base_where_filter);
		}

		// Setup query parameters (especially for sub-grids)
		$dataProvider->pagination->pageParam = $grid_id . '_page';
		$dataProvider->sort->sortParam       = $grid_id . '_sort';

		// Persistent paging
		if ( $persist_page ) {
			if ( $persistent_reset ) {
				// Reset parameter
				$session->remove( $session_key . '_page' );
			}

			// Get page number from query
			$page_number = $request->get( $dataProvider->pagination->pageParam, false );

			// If page_number is not in query, use persisted page selection
			if ($page_number === false) {
				$page_number = $session->get( $session_key . '_page', 0 );
				if ( $page_number <= $dataProvider->pagination->pageCount ) {
					$dataProvider->pagination->page = $page_number;
				}
			}

			// Set page number and persist it
			$session->set( $session_key . '_page', $page_number, $this->persist_grid_expiration );
		}

		// Persistent sorting
		if ( $persist_order ) {
			if ( $persistent_reset ) {
				// Reset parameter
				$session->remove( $session_key . '_sorting' );
			}

			// If no current order, use persisted order
			if ( ! $dataProvider->sort->getAttributeOrders() ) {
				$dataProvider->sort->setAttributeOrders( $session->get( $session_key . '_sorting', [] ) );
			}

			// Persist the current ordering
			$session->set( $session_key . '_sorting', $dataProvider->sort->getAttributeOrders(), $this->persist_grid_expiration );
		}

		return $dataProvider;
	}

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
	 * Adds a button which takes the user out of modal to a full page view for given controller action
	 *
	 * @param string $action
	 *
	 * @return string
	 */
	protected function modalToFullpageLink($action) {
		return $this->viewShowFullpageLink?
			Html::a( '<span class="glyphicon glyphicon-fullscreen" aria-hidden="true"></span>',
				yii\helpers\Url::to(
					[$action,  'id' => $this->model->id]
				)
			) . '&nbsp;':
			'';
	}

	/**
	 * View model record.
	 *
	 * @param integer $id
	 *
	 * @return string
	 */
	public function actionView( $id ) {
		$request = yii::$app->request;
		$this->findModel( $id );

		// Setup page title and first breadcrumb
		$this->view->title = yii::t( 'app', '{object} {name}', [
			'object' => $this->model_name,
			'name'   => ArrayHelper::getValue( $this->model, $this->model_field_name ),
		] );
		$this->addBreadCrumbs( [
			[ 'label' => $this->model_name . ' Overview', 'url' => [ 'index' ] ],
			ArrayHelper::getValue( $this->model, $this->model_field_name ),
		] );

		if ( $request->isAjax && ! $request->isPjax ) {
			// Ajax request
			yii::$app->response->format = Response::FORMAT_JSON;

			return [
				'title'   => $this->modalToFullpageLink('view') . $this->view->title,
				'content' => $this->renderAjax( 'view', $this->viewRenderData() ),
				'footer'  => $this->viewModalFooter(),
			];
		} else {
			// Non-ajax request
			return $this->render( 'view', $this->viewRenderData() );
		}
	}

	/**
	 * Builds view footer for modal after saved
	 *
	 * @return string
	 */
	protected function viewModalFooter() {
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
	 * Create a new model record
	 * @return mixed
	 */
	protected function newModel() {
		return new $this->modelClass();
	}

	/**
	 * Create response for ajax save
	 *
	 * @return array
	 */
	protected function createAjaxSavedResponse() {
		return [
			'forceReload' => $this->pjaxForceUpdateId(),
			'title'       => $this->view->title,
			'content'     => '<span class="text-success">' . yii::t( 'app', 'Create {object} success',
					[ 'object' => $this->model_name ] ) . '</span>',
			'footer'      => $this->createModalFooterSaved(),

		];
	}

	/**
	 * Creates a new model.
	 *
	 * @param string|null $return_url optional URL to return after success
	 *
	 * @return array|string|Response
	 */
	public function actionCreate( string $return_url = null ) {
		$request = yii::$app->request;

		// Setup URL to return after success
		if ( is_null( $return_url ) ) {
			$return_url = $this->createSuccessRedirect;
		}

		// Setup a new record
		$this->model = $this->newModel();

		// Setup page title and first breadcrumb
		$this->view->title = yii::t( 'app', 'Add New {object}', [ 'object' => $this->model_name ] );
		$this->addBreadCrumbs( [
			[ 'label' => $this->model_name . ' Overview', 'url' => [ 'index' ] ],
			yii::t( 'app', 'Create' ),
		] );

		// Set a model scenario if specified
		if ( isset( $this->model_create_scenario ) ) {
			$this->model->setScenario( $this->model_create_scenario );
		}

		if ( $request->isAjax && ! $request->isPjax ) {
			// Ajax request
			yii::$app->response->format = Response::FORMAT_JSON;

			// Load, validate and save model data
			if ( ! $request->isGet && $this->model->load( $request->post() ) && $this->model->save() ) {
				// Success
				if ( $return_url == 'index') {
					return [
						'forceReload' => $this->pjaxForceUpdateId(),
						'forceClose'  => true,
					];
				} elseif ( in_array( $return_url, ['view', 'update'] ) ) {
					// Success
					return [
						'forceReload' => $this->pjaxForceUpdateId(),
						'title'       => $this->view->title,
						'content'     => '<div class="text-success">' . yii::t( 'app', 'Create {object} success',
								[ 'object' => $this->model_name ] ) . '</div>'.
						                 $this->renderAjax(
							                 $return_url,
							                 $this->updateRenderData()
						                 ),
						'footer'      => $this->CreateModalFooterSaved(),
					];
				} else {
					return [
						'forceRedirect' => $return_url,
					];
				}
			} else {
				// Start (or fail) show form
				return [
					'title'   => $this->view->title,
					'content' => $this->renderAjax( 'create', $this->createRenderData() ),
					'footer'  => $this->createModalFooterEdit(),
				];
			}
		} else {
			//  Non-ajax request

			// Load, validate and save model data
			if ( $this->model->load( yii::$app->request->post() ) && $this->model->save() ) {
				// Success, go back to index
				return $this->redirect( [ $return_url, 'id' => $this->model->id ] );
			} else {
				// Start (or fail) show form
				return $this->render( 'create', $this->createRenderData() );
			}
		}
	}

	/**
	 * Copy from a new model.
	 *
	 * @param string|null $return_url optional URL to return after success
	 *
	 * @return string
	 */
	public function actionCopy( $id, string $return_url = null ) {
		$request = yii::$app->request;

		$this->findModel( $id );

		// Setup URL to return after success
		if ( is_null( $return_url ) ) {
			$return_url = $this->copySuccessRedirect;
		}

		// Mark record as new
		$this->model->id          = null;
		$this->model->isNewRecord = true;

		// Setup page title and first breadcrumb
		$this->view->title = yii::t( 'app', 'Create {object} copy of {name}', [
			'object' => $this->model_name,
			'name'   => ArrayHelper::getValue( $this->model, $this->model_field_name ),
		] );
		$this->addBreadCrumbs( [
			[ 'label' => $this->model_name . ' Overview', 'url' => [ 'index' ] ],
			yii::t( 'app', 'Copy' ),
		] );

		// Set a model scenario if specified
		if ( isset( $this->model_create_scenario ) ) {
			$this->model->setScenario( $this->model_create_scenario );
		}

		if ( $request->isAjax && ! $request->isPjax ) {
			// Ajax request
			yii::$app->response->format = Response::FORMAT_JSON;


			// Load, validate and save model data
			if ( ! $request->isGet && $this->model->load( $request->post() ) && $this->model->save() ) {
				// Success
				if ( $return_url == 'index') {
					// Back to grid: reload grid and close modal
					return [
						'forceReload' => $this->pjaxForceUpdateId(),
						'forceClose'  => true,
					];
				} elseif ( in_array( $return_url, ['view', 'update'] ) ) {
					// Another modal: reload grid and render content for other modal
					return [
						'forceReload' => $this->pjaxForceUpdateId(),
						'title'       => $this->view->title,
						'content'     => '<div class="text-success">' . yii::t( 'app', 'Copy {object} success',
								[ 'object' => $this->model_name ] ) . '</div>'.
						                 $this->renderAjax(
							                 $return_url,
							                 $this->updateRenderData()

						                 ),
						'footer'      => $this->CreateModalFooterSaved(),
					];
				} else {
					// A different return URL: redirect to this URL
					return [
						'forceRedirect' => $return_url,
					];
				}
			} else {
				// Start (or fail) show form
				return [
					'title'   => $this->view->title,
					'content' => $this->renderAjax( 'create', $this->createRenderData() ),
					'footer'  => $this->createModalFooterEdit(),

				];
			}
		} else {
			//  Non-ajax request
			if ( $this->model->load( yii::$app->request->post() ) && $this->model->save() ) {
				return $this->redirect( [ $return_url, 'id' => $this->model->id ] );
			} else {
				return $this->render( 'create', $this->createRenderData() );
			}
		}
	}

	/**
	 * Builds footer for modal after saved
	 *
	 * @return string
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
	 * Builds footer for modal on edit
	 *
	 * @return string
	 */
	protected function createModalFooterEdit() {
		return Html::button( yii::t('app', 'Close'), [
				'class'        => 'btn btn-default pull-left',
				'data-dismiss' => 'modal',
			] ) .
		       Html::button( yii::t('app', 'Create'), [ 'class' => 'btn btn-primary', 'type' => 'submit' ] );
	}

	/**
	 * Updates an existing model.
	 *
	 * @param integer $id model ID
	 * @param string|null $return_url optional URL to return after success
	 *
	 * @return string
	 */
	public function actionUpdate( $id, string $return_url = null  ) {
		$request = yii::$app->request;

		$this->findModel( $id );

		// Setup URL to return after success
		if ( is_null( $return_url ) ) {
			$return_url = $this->updateSuccessRedirect;
		}

		// Setup generic view settings
		$this->view->title = yii::t( 'app', 'Update {object} {name}', [
			'object' => $this->model_name,
			'name'   => ArrayHelper::getValue( $this->model, $this->model_field_name ),
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

		if ( $request->isAjax && ! $request->isPjax ) {
			// Ajax request
			yii::$app->response->format = Response::FORMAT_JSON;


			// Load, validate and save model data
			if ( ! $request->isGet && $this->model->load( $request->post() ) && $this->model->save() ) {
				// Success

				if ( $return_url == 'index') {
					// Back to grid: reload grid and close modal
					return [
						'forceReload' => $this->pjaxForceUpdateId(),
						'forceClose'  => true,
					];
				} elseif ( in_array( $return_url, ['view', 'update'] ) ) {
					// Another modal: reload grid and render content for other modal
					return [
						'forceReload' => $this->pjaxForceUpdateId(),
						'title'       => $this->view->title,
						'content'     => '<div class="text-success">' . yii::t( 'app', 'Update {object} success',
								[ 'object' => $this->model_name ] ) . '</div>'.
						                 $this->renderAjax(
							                 $return_url,
							                 $this->updateRenderData()
						                 ),
						'footer'      => $this->updateModalFooterSaved(),
					];
				} else {
					// A different return URL: redirect to this URL
					return [
						'forceRedirect' => $return_url,
					];
				}
			} else {
				// Start (or fail) show form
				return [
					'title' => $this->view->title,
					'content' => $this->renderAjax( 'update', $this->updateRenderData() ),
					'footer' => $this->updateModalFooterEdit(),
				];
			}
		} else {
			//  Non-ajax request
			if ( $this->model->load( $request->post() ) && $this->model->save() ) {
				// Success
				return $this->redirect( [ $return_url, 'id' => $this->model->id ] );
			} else {
				// Start (or fail) show form
				return $this->render( 'update', $this->updateRenderData() );
			}
		}
	}

	/**
	 * Builds footer for modal after saved
	 *
	 * @return string
	 */
	protected function updateModalFooterSaved() {
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
	 * Builds footer for modal on edit
	 *
	 * @return string
	 */
	protected function updateModalFooterEdit() {
		return Html::button( yii::t('app', 'Close'), [
				'class'        => 'btn btn-default pull-left',
				'data-dismiss' => 'modal',
			] ) .
		       Html::button( yii::t('app', 'Update'), [ 'class' => 'btn btn-primary', 'type' => 'submit' ] );
	}

	/**
	 * Deletes an existing model.
	 *
	 * @param integer $id model ID
	 * @param string|null $return_url optional URL to return after success
	 *
	 * @return string
	 */
	public function actionDelete( $id, string $return_url = null  ) {
		$request = yii::$app->request;

		$this->findModel( $id );

		// Setup URL to return after success
		if ( is_null( $return_url ) ) {
			$return_url = $this->deleteSuccessRedirect;
		}

		try {
			$this->model->delete();

			// Success
			if ( $request->isAjax && ! $request->isPjax ) {

				// Ajax request
				yii::$app->response->format = Response::FORMAT_JSON;

				if ( $return_url == 'index') {
					// Back to grid: reload grid and close modal
					return [
						'forceReload' => $this->pjaxForceUpdateId(),
						'forceClose'  => true,
					];
				} else {
					// A different return URL: redirect to this URL
					return [
						'forceRedirect' => $return_url,
					];
				}
			} else {
				// Non-ajax request
				return $this->redirect( [ $return_url ] );
			}
		} catch ( \Exception $e ) {

			// Build error message
			if ( $e->getCode() === 23000 && preg_match( '/CONSTRAINT `(.*)` FOREIGN/', $e->getMessage(),
					$matches ) === 1
			) {
				// Handle SQL foreign key errors
				$error = yii::t( 'app', 'The record is linked with {foreign_key}. Unlink before delete.',
					[ 'foreign_key' => $matches[1] ] );
			} else {
				$error = $e->getMessage();
			}

			if ( $request->isAjax && ! $request->isPjax ) {
				// Ajax request
				yii::$app->response->format = Response::FORMAT_JSON;

				// Show error message in modal
				return [
					'title'   => yii::t( 'app', 'Delete failed' ),
					'content' => yii::t( 'app', 'Error: {message}', [ 'message' => $error ] ),
					'footer'  => Html::button( 'Close', [
						'class'        => 'btn btn-default pull-left',
						'data-dismiss' => 'modal',
					] ),
				];
			} else {
				// Non-ajax request
				yii::$app->getSession()->setFlash( 'error',
					yii::t( 'app', 'Delete failed, error: {message}',
						[ 'message' => $error ]
					)
				)
				;

				return $this->redirect( [ $return_url ] );
			}
		}
	}

	/**
	 * Delete multiple existing model.
	 * For ajax request will return json object
	 *
	 * @param string|null $return_url optional URL to return after success
	 *
	 * @return string
	 */
	public function actionBulkDelete( string $return_url = null ) {
		$request = yii::$app->request;

		// Setup URL to return after success
		if ( is_null( $return_url ) ) {
			$return_url = $this->bulkDeleteSuccessRedirect;
		}

		$not_found = [];

		// For all given id's (pks)
		$pks = explode( ',', $request->post( 'pks' ) );
		foreach ( $pks as $pk ) {
			try {
				// Get the model and delete
				$this->findModel( $pk );
				$this->model->delete();
			} catch ( yii\base\Exception $e ) {
				$not_found[] = $pk;
			}
		}

		if ( $request->isAjax && ! $request->isPjax ) {
			// Ajax request
			yii::$app->response->format = Response::FORMAT_JSON;

			if ( $return_url == 'index') {
				// Back to grid: reload grid and close modal
				return [
					'forceReload' => $this->pjaxForceUpdateId(),
					'forceClose'  => true,
					'message'     => count( $not_found ) ? 'Not found: ' . implode( ',', $not_found ) : '',
				];
			} else {
				// A different return URL: redirect to this URL
				return [
					'forceRedirect' => $return_url,
				];
			}
		} else {
			// Non-ajax request
			return $this->redirect( [ $return_url ] );
		}
	}

	/**
	 * Process bulk updates
	 * For ajax request will return json object
	 *
	 * @param string|null $return_url optional URL to return after success
	 *
	 * @return \yii\web\Response|array
	 *
	 * @throws NotFoundHttpException
	 */
	public function actionBulkUpdate( string $return_url = null ) {
		$request = yii::$app->request;

		// Setup URL to return after success
		if ( is_null( $return_url ) ) {
			$return_url = $this->bulkUpdateSuccessRedirect;
		}

		// Parse the fields to update
		$update_attribute_value = [];
		$Model                  = new $this->modelClass;
		foreach ( $Model->activeAttributes() as $attribute ) {
			// Check if a value is provided
			if ( ! is_null(yii::$app->request->post( $attribute ) ) ) {
				$update_attribute_value[ $attribute ] = yii::$app->request->post( $attribute );
			}
		}

		// CHeck if there are fields to update
		if ( ! $update_attribute_value ) {
			yii::$app->session->setFlash( 'alert', [
				'body'    => yii::t( 'app', 'No fields found to update.' ),
				'options' => [ 'class' => 'alert-warning' ],
			] );

			return $this->redirect( [ 'index' ] );
		}

		// Update the fields for all the selected records (pks)
		$errors = [];
		$records_updated = 0;
		foreach ( explode( ',', $request->post( 'pks' ) ) as $id ) {
			$this->findModel( (int) $id, false );

			if ( $this->model ) {
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
						$errors[] = $this->model->getErrors();
					} else {
						// Track updates
						$records_updated++;
					}
				}
			} else {
				// Track if record is not found
				$errors[] = 'id ' . $id . ' not found!';
			}
		}

		if ( $request->isAjax && ! $request->isPjax ) {
			// Ajax request
			yii::$app->response->format = Response::FORMAT_JSON;

			// Check if errors are found
			if ( $errors ) {
				return [
					'forceReload' => $this->pjaxForceUpdateId(),
					'title'   => yii::t( 'app', 'Update failed' ),
					'content' => yii::t( 'app', 'Error(s): {errors}', [
						'errors' => print_r( $errors, true )
					] ),
					'footer'  => Html::button( 'Close', [
						'class'        => 'btn btn-default pull-left',
						'data-dismiss' => 'modal',
					] ),
				];
			} else {
				if ( $return_url == 'index') {
					// Back to grid: reload grid and show results
					return [
						'forceReload' => $this->pjaxForceUpdateId(),
						'title'   => yii::t( 'app', 'Bulk update succesful' ),
						'content' => yii::t( 'app', '{records} records updated', [
							'records' => $records_updated
						] ),
						'footer'  => Html::button( 'Close', [
							'class'        => 'btn btn-default pull-left',
							'data-dismiss' => 'modal',
						] ),
					];
				} else {
					// A different return URL: redirect to this URL
					return [
						'forceRedirect' => $return_url,
					];
				}
			}
		} else {
			// Non-ajax request

			// Check if errors are found
			if ( $errors ) {
				yii::$app->session->setFlash( 'alert', [
					'body'    => yii::t( 'app', 'Bulk update failed: {errors}.', [
						'errors' => print_r( $errors, true ),
					] ),
					'options' => [ 'class' => 'alert-danger' ],
				] );
			} else {
				yii::$app->session->setFlash( 'alert', [
					'body'    => yii::t( 'app', 'Bulk update successful. {records} records updated', [
						'records' => $records_updated
					] ),
					'options' => [ 'class' => 'alert-success' ],
				] );
			}

			return $this->redirect( [ $return_url ] );
		}
	}

	/**
	 * Finds the model based on its primary key value.
	 * If the model is not found, a 404 HTTP exception will be thrown.
	 *
	 * @param integer $id
	 *
	 * @throws NotFoundHttpException if the model cannot be found
	 */
	protected function findModel( $id, $throw_not_found = true ) {
		$modelClass = $this->modelClass;
		$this->model = $modelClass::findOne( $id );
		if ( ! $this->model && $throw_not_found ) {
			throw new NotFoundHttpException( 'The requested page does not exist.' );
		}
	}
}