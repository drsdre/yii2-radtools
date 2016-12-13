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

/**
 * Implements Ajax CRUD actions for a model.
 */
class BaseAjaxCrudController extends Controller {
	protected $useDynagrid = false;
	protected $modelClass;
	protected $searchModelClass;
	protected $model_name;
	protected $model_field_name;

	protected $model;

	/**
	 * ID for pjax forceUpdate
	 *
	 * @return string
	 */
	protected function pjaxForceUpdateId() {
		if ($this->useDynagrid) {
			$searchModel  = new $this->searchModelClass();
			return '#'.$searchModel::tableName().'-gridview-pjax';
		} else {
			return '#crud-datatable-pjax';
		}
	}

	/**
	 * Session key for filter persistence
	 *
	 * @return string
	 */
	public function filtersSessionKey() {
		return 'AjaxCrudCont_filters_'.$this->model_name;
	}

	/**
	 * Add breadcrumbs to the view
	 *
	 * @param array $crumbs
	 */
	public function addBreadCrumbs($crumbs) {
		foreach($crumbs as $crumb) {
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
	protected function indexEditableOutput(array $posted, $model) {
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
	protected function indexDataProvider($searchModel) {
		$request = Yii::$app->request;
		$session = Yii::$app->session;

		// Clear query filters on filter reset
		if ( $request->get('filter_reset', false) ) {
			$session->set($this->filtersSessionKey(), []);
		} elseif ( ! isset( $request->queryParams[$searchModel->formName()] ) ) {
			// Load filters from user and from links
			$searchModel->setAttributes($session->get($this->filtersSessionKey()));
		}

		// Execute search
		$dataProvider = $searchModel->search(Yii::$app->request->queryParams);

		// Persist query filters from search (if filters were specified)
		if ( isset( $request->queryParams[$searchModel->formName()] ) ) {
			$session->set($this->filtersSessionKey(), $searchModel->getAttributes());
		}

		// Load filters from user and from links
		return $dataProvider;
	}

	/**
	 * Lists all records (for gridview)
	 *
	 * @return string
	 */
	public function actionIndex() {
		$request = Yii::$app->request;

		// Setup page title and first breadcrumb
		$this->view->title = Yii::t( 'app', "{object} Overview", [
			'object' => $this->model_name,
		] );
		$this->addBreadCrumbs([$this->view->title]);

		// Setup data feed
		$searchModel  = new $this->searchModelClass();
		$dataProvider = $this->indexDataProvider($searchModel);

		// Validate if there is input from editable field saved through AJAX
		if ( $request->post( 'hasEditable' ) ) {
			$this->findModel( $request->post( 'editableKey' ) );

			// Fetch the first entry in posted data
			$search_parent_model = get_parent_class($searchModel);
			$searchParentModel = new $search_parent_model;
			$posted = current( $request->post($searchParentModel->formName() ) );

			// Load model
			if ( $this->model->load( [ $this->model->formName() => $posted ] ) ) {

				// Can save model or do something before saving model
				if ($this->model->save()) {
					$out = Json::encode( [ 'output' => $this->indexEditableOutput($posted, $this->model), 'message' => '' ] );
				} else {
					$out = Json::encode( [ 'output' => $this->indexEditableOutput($posted, $this->model), 'message' => 'Error' ] );
				}

			} else {
				// Default json response
				$out = Json::encode( [ 'output' => '', 'message' => '' ] );
			}

			// Return ajax json encoded response
			return $out;
		}

		return $this->render( 'index', $this->indexRenderData($searchModel, $dataProvider) );
	}

	/**
	 * View model record.
	 *
	 * @param integer $id
	 *
	 * @return string
	 */
	public function actionView( $id ) {
		$request = Yii::$app->request;
		$this->findModel( $id );

		// Setup page title and first breadcrumb
		$this->view->title   = Yii::t( 'app', "View {object} {name}", [
			'object' => $this->model_name,
			'name'   => $this->model->{$this->model_field_name}
		] );
		$this->addBreadCrumbs([
			['label' => $this->model_name. ' Overview', 'url' => ['index']],
			$this->model->{$this->model_field_name}
		]);

		if ( $request->isAjax && ! $request->isPjax  ) {
			// Ajax request
			Yii::$app->response->format = Response::FORMAT_JSON;

			return [
				'title'   => $this->view->title,
				'content' => $this->renderAjax( 'view', $this->viewRenderData() ),
				'footer'  => $this->viewModalFooter()
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
		return Html::button( 'Close', [
			'class'        => 'btn btn-default pull-left',
			'data-dismiss' => "modal"
		] ) .
		       Html::a( 'Edit', [ 'update', 'id' => $this->model->id ], [
			       'class' => 'btn btn-primary',
			       'role'  => 'modal-remote'
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
			'content'     => '<span class="text-success">' . Yii::t( 'app', 'Create {object} success',
					[ 'object' => $this->model_name ] ) . '</span>',
			'footer'      => $this->createModalFooterSaved()

		];
	}

	/**
	 * Creates a new model.
	 * If creation is successful, the browser will be redirected to the 'index' page.
	 *
	 * @return string
	 */
	public function actionCreate() {
		$request = Yii::$app->request;

		// Setup a new record
		$this->model   = $this->newModel();

		// Setup page title and first breadcrumb
		$this->view->title = Yii::t( 'app', "Add New {object}", [ 'object' => $this->model_name ] );
		$this->addBreadCrumbs([
			['label' => $this->model_name. ' Overview', 'url' => ['index']],
			Yii::t('app', 'Create')
		]);

		if ( $request->isAjax && ! $request->isPjax ) {
			// Ajax request
			Yii::$app->response->format = Response::FORMAT_JSON;

			// Set a model scenario if specified
			if (isset($this->model_create_scenario)) {
				$this->model->setScenario($this->model_create_scenario);
			}

			// Load, validate and save model data
			if ( ! $request->isGet && $this->model->load( $request->post() ) && $this->model->save() ) {
				// Success
				return [
					'forceReload' => $this->pjaxForceUpdateId(),
					'forceClose' => true,
					//'title'       => $this->view->title,
					//'content'     => '<span class="text-success">' . Yii::t( 'app', 'Create {object} success',
					//		[ 'object' => $this->model_name ] ) . '</span>',
					//'footer'      => $this->CreateModalFooterSaved()

				];
			} else {
				// Start (or fail) show form
				return [
					'title'   => $this->view->title,
					'content' => $this->renderAjax( 'create', $this->createRenderData() ),
					'footer'  => $this->createModalFooterEdit()

				];
			}
		} else {
			//  Non-ajax request

			// Load, validate and save model data
			if ( $this->model->load( Yii::$app->request->post() ) && $this->model->save() ) {
				// Success, go back to index
				return $this->redirect( [ 'index', 'id' => $this->model->id ] );
			} else {
				// Start (or fail) show form
				return $this->render( 'create', $this->createRenderData() );
			}
		}
	}

	/**
	 * Copy from a new model.
	 * If creation is successful, the browser will be redirected to the 'index' page.
	 *
	 * @return string
	 */
	public function actionCopy($id) {
		$request = Yii::$app->request;

		$this->findModel( $id );

		// Mark record as new
		$this->model->id = null;
		$this->model->isNewRecord = true;

		// Setup page title and first breadcrumb
		$this->view->title = Yii::t( 'app', "Create {object} copy of {name}", [
			'object' => $this->model_name,
			'name'   => $this->model->{$this->model_field_name}
		] );
		$this->addBreadCrumbs([
			['label' => $this->model_name. ' Overview', 'url' => ['index']],
			Yii::t('app', 'Copy')
		]);

		if ( $request->isAjax && ! $request->isPjax ) {
			// Ajax request
			Yii::$app->response->format = Response::FORMAT_JSON;

			// Set a model scenario if specified
			if (isset($this->model_create_scenario)) {
				$this->model->setScenario($this->model_create_scenario);
			}

			// Load, validate and save model data
			if ( ! $request->isGet && $this->model->load( $request->post() ) && $this->model->save() ) {
				// Success
				return [
					'forceReload' => $this->pjaxForceUpdateId(),
					'title'       => $this->view->title,
					'content'     => '<span class="text-success">' . Yii::t( 'app', 'Copy {object} success',
							[ 'object' => $this->model_name ] ) . '</span>',
					'footer'      => $this->createModalFooterSaved()

				];
			} else {
				// Start (or fail) show form
				return [
					'title'   => $this->view->title,
					'content' => $this->renderAjax( 'create', $this->createRenderData() ),
					'footer'  => $this->createModalFooterEdit()

				];
			}
		} else {
			//  Non-ajax request
			if ( $this->model->load( Yii::$app->request->post() ) && $this->model->save() ) {
				return $this->redirect( [ 'index', 'id' => $this->model->id ] );
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
		return Html::button( 'Close', [
			'class'        => 'btn btn-default pull-left',
			'data-dismiss' => "modal"
		] ) .
		       Html::a( 'Edit', [ 'update', 'id' => $this->model->id ], [
			       'class' => 'btn btn-primary',
			       'role'  => 'modal-remote'
		       ] );
	}

	/**
	 * Builds footer for modal on edit
	 *
	 * @return string
	 */
	protected function createModalFooterEdit() {
		return Html::button( 'Close', [
			'class'        => 'btn btn-default pull-left',
			'data-dismiss' => "modal"
		] ) .
		       Html::button( 'Create', [ 'class' => 'btn btn-primary', 'type' => "submit" ] );
	}

	/**
	 * Updates an existing model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 *
	 * @param integer $id
	 *
	 * @return string
	 */
	public function actionUpdate( $id ) {
		$request = Yii::$app->request;
		$this->findModel( $id );

		// Setup generic view settings
		$this->view->title = Yii::t( 'app', "Update {object} {name}", [
			'object' => $this->model_name,
			'name'   => $this->model->{$this->model_field_name}
		] );
		$this->addBreadCrumbs([
			['label' => $this->model_name. ' Overview', 'url' => ['index']],
			['label' => $this->model->{$this->model_field_name}, 'url' => ['view', 'id' => $this->model->id]],
			Yii::t('app', 'Update')
		]);

		if ( $request->isAjax && ! $request->isPjax ) {
			// Ajax request
			Yii::$app->response->format = Response::FORMAT_JSON;

			// Set a model scenario if specified
			if (isset($this->model_update_scenario)) {
				$this->model->setScenario($this->model_update_scenario);
			}

			// Load, validate and save model data
			if ( ! $request->isGet && $this->model->load( $request->post() ) && $this->model->save() ) {
				// Success
				return [
					'forceReload' => $this->pjaxForceUpdateId(),
					'title'       => $this->view->title,
					'content'     => $this->renderAjax( 'view', $this->updateRenderData() ),
					'footer'      => $this->updateModalFooterSaved()
				];
			} else {
				// Start (or fail) show form
				return [
					'title'   => $this->view->title,
					'content' => $this->renderAjax( 'update', $this->updateRenderData() ),
					'footer'  => $this->updateModalFooterEdit()
				];
			}
		} else {
			//  Non-ajax request
			if ( $this->model->load( $request->post() ) && $this->model->save() ) {
				// Success
				return $this->redirect( [ 'view', 'id' => $this->model->id ] );
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
		return Html::button( 'Close', [
			'class'        => 'btn btn-default pull-left',
			'data-dismiss' => "modal"
		] ) .
		       Html::a( 'Edit', [ 'update', 'id' => $this->model->id ], [
			       'class' => 'btn btn-primary',
			       'role'  => 'modal-remote'
		       ] );
	}

	/**
	 * Builds footer for modal on edit
	 *
	 * @return string
	 */
	protected function updateModalFooterEdit() {
		return Html::button( 'Close', [
			'class'        => 'btn btn-default pull-left',
			'data-dismiss' => "modal"
		] ) .
		       Html::button( 'Update', [ 'class' => 'btn btn-primary', 'type' => "submit" ] );
	}

	/**
	 * Deletes an existing model.
	 * If deletion is successful, the browser will be redirected to the 'index' page.
	 *
	 * @param integer $id
	 *
	 * @return string
	 */
	public function actionDelete( $id ) {
		$request = Yii::$app->request;
		$this->findModel( $id );
		try {
			$this->model->delete();
			if ( $request->isAjax && ! $request->isPjax ) {
				// Ajax request
				Yii::$app->response->format = Response::FORMAT_JSON;

				return [ 'forceClose' => true, 'forceReload' => '#crud-datatable-pjax', ];
			} else {
				// Non-ajax request
				return $this->redirect( [ 'index' ] );
			}
		} catch (\Exception $e) {

			// Build error message
			if ( $e->getCode() === 23000 && preg_match('/CONSTRAINT `(.*)` FOREIGN/', $e->getMessage(), $matches) === 1) {
				// Handle SQL foreign key errors
				$error = Yii::t('app', 'The record is linked with {foreign_key}. Unlink before delete.', ['foreign_key' => $matches[1]]);
			} else {
				$error = $e->getMessage();
			}

			if ( $request->isAjax && ! $request->isPjax ) {
				// Ajax request
				Yii::$app->response->format = Response::FORMAT_JSON;

				return [
					'title'   => Yii::t('app', 'Delete failed'),
					'content' => Yii::t('app', 'Error: {message}', ['message' => $error]),
					'footer'  => Html::button( 'Close', [
						'class'        => 'btn btn-default pull-left',
						'data-dismiss' => "modal"
					] )
				];
			} else {
				// Non-ajax request
				Yii::$app->getSession()->setFlash('error',
					Yii::t('app', 'Delete failed, error: {message}',
						['message' => $error]
					)
				);
				return $this->redirect( [ 'index' ] );
			}
		}

	}

	/**
	 * Delete multiple existing model.
	 * For ajax request will return json object
	 * and for non-ajax request if deletion is successful, the browser will be redirected to the 'index' page.
	 *
	 * @return string
	 */
	public function actionBulkDelete() {
		$request    = Yii::$app->request;

		$not_found = [];

		// For all given id's (pks)
		$pks        = explode(',', $request->post( 'pks' ));
		foreach ( $pks as $pk ) {
			try {
				// Get the model and delete
				$this->findModel($pk);
				$this->model->delete();
			} catch (yii\base\Exception $e) {
				$not_found[] = $pk;
			}
		}

		if ( $request->isAjax && ! $request->isPjax ) {
			// Ajax request
			Yii::$app->response->format = Response::FORMAT_JSON;

			return [
				'forceReload' => $this->pjaxForceUpdateId(),
				'forceClose' => true,
				'message' => count($not_found)?'No found: '.implode(',', $not_found):'',
			];
		} else {
			// Non-ajax request
			return $this->redirect( [ 'index' ] );
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
	protected function findModel( $id ) {
		$modelClass = $this->modelClass;
		if ( ! $this->model = $modelClass::findOne( $id ) ) {
			throw new NotFoundHttpException( 'The requested page does not exist.' );
		}
	}
}
