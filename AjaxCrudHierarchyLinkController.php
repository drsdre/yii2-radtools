<?php
/**
 * Ajax Crud Hierachy Link Controller
 * Provides a linking mechanism between related tables through GET parameter
 * hierarchy_filter[link_variable]=link_id.
 *
 * By: A.F.Schuurman
 * Date: 2016-12-07
 */

namespace drsdre\radtools;

use yii;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

class AjaxCrudHierarchyLinkController extends BaseAjaxCrudController {

	/** @var string $grid_hierarchy_get_param name of query parameter for setting persistent hierarchy */
	public $grid_hierarchy_get_param = 'hierarchy_filter';

	/** @var string $grid_hierarchy_reset_get_param name of query parameter for resetting persistent hierarchy */
	public $grid_hierarchy_reset_get_param = 'reset_grid_hierarchy';

	/** @var string $hierarchy_link_session_key key id for caching persistent hierarchy link parameters */
	public $hierarchy_link_session_key = 'HierarchyLink';

	/**
	 * @var array $hierarchy_links
	 * Define a list of hierarchy links in the controller like this:
	 *  protected $hierarchy_links = ['field id' =>     Defines the query parameter for the hierarchy link
	 *      'model' => '\namespace\ActiveRecordModel',  Defines the top parent record for the hierarchy link
	 *      'linked_model' => 'linkModel',              Defines the function to get from current record to parent record
	 *      'breadcrumbs' => [                          Defines one or more additional breadcrumbs to show hierarchy
	 *      [
	 *          'label' => 'Overview',              Label of Breadcrumb
	 *          'url' => '/record/',                Url of Breadcrumb
	 *          ],
	 *      [
	 *          'label' => '{model_name}',          {model-name} will be replaced by name of parent record field name
	 *          'name_field' => 'name',             Field name for name of parent record
	 *          'url' => '/record/view?id={id}',    {id} will be replaced by id of parent record
	 *      ]
	 *    'field id2' => etc.
	 *  ]
	 */
	protected $hierarchy_links = [];

	/** @var array $active_hierarchy_filters search query filters for DataProvider */
	protected $active_hierarchy_filters = [];

	/** @var array $active_hierarchy_records model records of (active) links */
	protected $active_hierarchy_records = [];

	/**
	 * @inheritdoc
	 */
	public function init() {
		parent::init();
		$this->parseLinksFromUrl();
	}

	/**
	 * Parses link parameters from URL and stored them in session for hierarchy links
	 */
	protected function parseLinksFromUrl() {
		// If no hierarchy filter specified in controller, skip the parsing
		if ( ! isset( $this->hierarchy_links ) ) {
			return;
		}

		$session = Yii::$app->session;

		// Get current filters
		$this->active_hierarchy_filters = $session->get( $this->hierarchy_link_session_key, [] );

		// Do filters need to be reset?
		if ( Yii::$app->request->getQueryParam( $this->grid_hierarchy_reset_get_param ) ) {
			foreach ( $this->hierarchy_links as $field => $value ) {
				// Reset the defined filters for this controller
				unset( $this->active_hierarchy_filters[ $field ] );
			}
			// Store the filters
			$session->set( $this->hierarchy_link_session_key, $this->active_hierarchy_filters );
		}

		// Process incoming filters
		$new_hierarchy_filters = Yii::$app->request->getQueryParam( $this->grid_hierarchy_get_param );
		if ( $new_hierarchy_filters ) {
			foreach ( $new_hierarchy_filters as $field => $value ) {
				// Add recognized filters to session
				if ( array_key_exists( $field, $this->hierarchy_links ) ) {

					// Check if filter for other fields need to be reset
					if ( isset( $this->hierarchy_links[ $field ]['reset_fields'] ) ) {
						foreach ( $this->hierarchy_links[ $field ]['reset_fields'] as $reset_field ) {
							unset( $this->active_hierarchy_filters[ $reset_field ] );
						}
					}
					// Check if a filter value is specified
					if ( ! is_null( $value ) || $value !== '' ) {
						// Add new filter
						$this->active_hierarchy_filters[ $field ] = $value;
					} else {
						// Unset filter if no value given
						unset( $this->active_hierarchy_filters[ $field ] );
					}
				}
			};
			// Store the filters
			$session->set( $this->hierarchy_link_session_key, $this->active_hierarchy_filters );
		}

		// Add debugging
		Yii::trace( $session->get( $this->hierarchy_link_session_key ),
			'drsdre\radtools\HierarchyLinkTrait::parseLinks' );

		// Make linked models avaiable in the view
		$this->view->params['hierarchy_records'] = $this->getActiveLinkedModels();
	}

	/**
	 * Retrieve active linked models
	 *
	 * @param ActiveRecord|null $current_model
	 *
	 * @return array
	 */
	public function getActiveLinkedModels( $current_model = null ) {

		// If linked model were calculated, skip processing
		if ( count( $this->active_hierarchy_records ) ) {
			return $this->active_hierarchy_records;
		}

		if ( ! isset( $this->hierarchy_links ) ) {
			return [];
		}

		// Load models for the filter links
		$this->active_hierarchy_records = [];
		foreach ( $this->hierarchy_links as $filter_variable => $filter_model ) {
			// Check if a hierarchy filter was provided for linked model
			if ( isset( $this->active_hierarchy_filters[ $filter_variable ] ) ) {
				// Add the record which matches the filter
				$this->active_hierarchy_records[ $filter_variable ] =
					$filter_model['model']::findOne( $this->active_hierarchy_filters[ $filter_variable ] );
				// Check if linked model is available linked to main model
			} elseif ( $current_model && $current_model->$filter_model['linked_model'] ) {
				// Add the linked record to main model
				$this->active_hierarchy_records[ $filter_variable ] = $current_model->$filter_model['linked_model'];
			}
		}

		return $this->active_hierarchy_records;
	}

	/**
	 * Get the active model for link_id if available
	 *
	 * @param string $link_id
	 *
	 * @return ActiveRecord
	 */
	public function getActiveLinkedModel( $link_id ) {
		if ( $this->isLinkActive( $link_id ) ) {
			$this->getActiveLinkedModels();

			return isset( $this->active_hierarchy_records[ $link_id ] ) ? $this->active_hierarchy_records[ $link_id ] : null;
		}
	}

	/**
	 * Check if hierarchy link is active for link_id
	 *
	 * @param string $link_id
	 *
	 * @return boolean
	 */
	public function isLinkActive( $link_id ) {
		return isset( $this->active_hierarchy_filters[ $link_id ] );
	}

	/**
	 * Add view breadcrumbs including active hierarchy links breadcrumbs
	 *
	 * @param array $additional_breadcrumbs
	 */
	public function addBreadCrumbs( $additional_breadcrumbs = [] ) {
		// Gather breadcrumbs for all linked models
		foreach ( $this->getActiveLinkedModels() as $filter_variable => $model ) {
			if ( ! isset( $this->hierarchy_links[ $filter_variable ]['breadcrumbs'] ) || is_null( $model ) ) {
				continue;
			}
			// For each hierarchy check if there is
			foreach ( $this->hierarchy_links[ $filter_variable ]['breadcrumbs'] as $breadcrumb_def ) {
				// Only hierarchy filters with label are processed
				if ( isset( $breadcrumb_def['label'] ) ) {
					// Setup breadcrumb label
					$label = $breadcrumb_def['label'];
					if ( $label instanceof \Closure ) {
						// Execute function
						$breadcrumb = [
							'label' => $label( $model ),
						];
					} elseif ( isset( $breadcrumb_def['name_field'] ) ) {
						// Replace {model_name} in label with value
						$breadcrumb = [
							'label' => Yii::t(
								'app',
								$label,
								[
									'model_name' => yii\helpers\ArrayHelper::getValue( $model,
										$breadcrumb_def['name_field'] ),
								]
							),
						];
					} else {
						$breadcrumb = [ 'label' => $label ];
					}

					// Setup breadcrumb url
					if ( isset( $breadcrumb_def['url'] ) ) {
						// Add url to breadcrumb definition
						$breadcrumb['url'] = $breadcrumb_def['url'];

						// Find field references in url
						preg_match_all( '/\{(.*?)\}/', $breadcrumb['url'], $replacements );
						foreach ( $replacements[0] as $key => $replacement ) {
							if ( count( $replacement ) > 0 ) {
								// Replace reference '{<fieldname>}' with value from model
								$breadcrumb['url'] = strtr( $breadcrumb['url'],
									[
										$replacements[0][ $key ] => ArrayHelper::getValue( $model,
											$replacements[1][ $key ] ),
									] );
							}
						}
					}
					// Add to the view breadcrumbs array
					$this->view->params['breadcrumbs'][] = $breadcrumb;
				}
			}
		}
		// Add additional breadcrumbs to the end
		foreach ( $additional_breadcrumbs as $crumb ) {
			$this->view->params['breadcrumbs'][] = $crumb;
		}
	}

	/**
	 * @inheritdoc
	 *
	 * Filters dataProvider result set with active hierarchy links
	 */
	protected function indexDataProvider( ActiveRecord $searchModel ) {

		$dataProvider = parent::indexDataProvider( $searchModel );

		// Skip if no hierarchy links are set
		if ( ! isset( $this->hierarchy_links ) ) {
			return $dataProvider;
		}

		// Apply active hierarchy links to dataProvider
		foreach ( $this->hierarchy_links as $field => $hierarchy_link ) {

			// Skip if hierarchy filter is not active
			if ( ! isset( $this->active_hierarchy_filters[ $field ] ) ) {
				continue;
			}
			// Check if field is defined in native model or external model
			if ( isset( $hierarchy_link['index_query_external_model'] ) ) {
				// External
				$model = $hierarchy_link['index_query_external_model'];
			} else {
				// Native
				$model = $this->searchModelClass;
			}

			// Add hierarchy link as where clause to the query
			$modelObject = new $model;
			if ( $modelObject->isAttributeActive( $field ) ) {
				$dataProvider->query->andWhere(
					[ $model::tableName() . '.' . $field => $this->active_hierarchy_filters[ $field ] ]
				);
			}

			// If field is available in searchModel, set it to make it visible in grid filter
			if ( $searchModel->isAttributeActive( $field ) ) {
				$searchModel->$field = $this->active_hierarchy_filters[ $field ];
			}
		}

		return $dataProvider;
	}

	/**
	 * New model with default values from active linked models
	 *
	 * @return ActiveRecord
	 */
	protected function newModel() {
		// Get model
		$model = parent::newModel();

		// Skip processing if no filter link models or links are available
		if ( ! isset( $this->hierarchy_links ) || count( $this->hierarchy_links ) == 0 ) {
			return $model;
		}

		// Load models for the filter links
		foreach ( $this->hierarchy_links as $filter_variable => $filter_model ) {
			if (
				isset( $this->active_hierarchy_filters[ $filter_variable ] ) &&
				$model->isAttributeActive( $filter_variable )
			) {
				$model->$filter_variable = $this->active_hierarchy_filters[ $filter_variable ];
			}
		}

		return $model;
	}
}