<?php
/**
 * Hierachy Link Trait
 * Provides a linking mechanism between related tables through the GET parameter hierarchy_filter[link_variable]=link_id.
 * Should be side loaded in class through 'use drsdre\radtools\HierarchyLinkController.
 *
 * By: A.F.Schuurman
 * Date: 2016-12-07
 */

namespace drsdre\radtools;

use yii;
use yii\db\ActiveRecord;

trait HierarchyLinkTrait {

	static $link_session_key = 'HierarchyLink';

	/** @var string $session_key */
	private $session_key;

	/** @var array $hierarchy_filters search query filters for DataProvider */
	protected $hierarchy_filters = [];

	/** @var array $hierarchy_records model records of (active) links */
	protected $hierarchy_records = [];

	/**
	 * @
	 */
	public function init() {
		parent::init();
		$this->parseLinks();
	}

	/**
	 * Parses link parameters from URL and stored them in session for hierarchy links
	 */
	protected function parseLinks() {
		// If no hierarchy filter specified in controller, skip the parsing
		if (!isset($this->hierarchy_links)) {
			return;
		}

		$session = Yii::$app->session;
		$this->hierarchy_filters = $session->has(self::$link_session_key)?$session->get(self::$link_session_key):[];

		// Do filters need to be reset?
		if (Yii::$app->request->getQueryParam('hierarchy_filter_reset')) {
			foreach($this->hierarchy_links as $field => $value) {
				// Reset the defined filters for this controller
				unset($this->hierarchy_filters[$field]);
			}
			// Store the filters
			$session->set(self::$link_session_key, $this->hierarchy_filters);
		}

		// Process incoming filters
		$new_hierarchy_filters = Yii::$app->request->getQueryParam('hierarchy_filter');
		if ($new_hierarchy_filters) {
			foreach($new_hierarchy_filters as $field => $value) {
				// Add recognized filters to session
				if (array_key_exists($field, $this->hierarchy_links)) {

					// Check if filter for other fields need to be reset
					if (isset($this->hierarchy_links[$field]['reset_fields'])) {
						foreach($this->hierarchy_links[$field]['reset_fields'] as $reset_field) {
							unset($this->hierarchy_filters[$reset_field]);
						}
					}
					// Check if a filter value is specified
					if ( !is_null($value) || $value !== '') {
						// Add new filter
						$this->hierarchy_filters[$field] = $value;
					} else {
						// Unset filter if no value given
						unset($this->hierarchy_filters[$field]);
					}
				}
			};
			// Store the filters
			$session->set(self::$link_session_key, $this->hierarchy_filters);
		}

		// Add debugging
			Yii::trace($session->get(self::$link_session_key), 'drsdre\radtools\HierarchyLinkTrait::parseLinks');

		// Make linked models avaiable in the view
		$this->view->params['hierarchy_records'] = $this->getLinkedModels();
	}

	/**
	 * @inheritdoc
	 */
	protected function indexDataProvider($searchModel) {

		$dataProvider = parent::indexDataProvider($searchModel);

		if (!isset($this->hierarchy_links)) {
			return $dataProvider;
		}

		// Test all filter models
		foreach ($this->hierarchy_links as $field => $filter_settings) {
			// Check if a filter link is active
			if (!isset($this->hierarchy_filters[$field])) {
				continue;
			}
			// Check if field is defined in native model or external model
			if (isset($filter_settings['index_query_external_model'])) {
				$model = $filter_settings['index_query_external_model'];
			} else {
				$model = $this->searchModelClass;
			}

			$modelObject = new $model;
			if ($modelObject->isAttributeActive($field)) {
				// Add filter to the query
				$dataProvider->query->andWhere([$model::tableName().'.'.$field => $this->hierarchy_filters[$field]]);
			}

			// If field is available in the searchModel, set it
			if ($searchModel->isAttributeActive($field)) {
				$searchModel->$field = $this->hierarchy_filters[$field];
			}
		}

		return $dataProvider;
	}

	/**
	 * Check if hierarchy link is active for link_id
	 *
	 * @param string $link_id
	 *
	 * @return boolean
	 */
	public function isLinkActive($link_id) {
		return isset($this->hierarchy_filters[$link_id]);
	}


	/**
	 * Get the model for link_id if available
	 *
	 * @param string $link_id
	 *
	 * @return ActiveRecord
	 */
	public function getLinkedModel($link_id) {
		if ($this->isLinkActive($link_id)) {
			$this->getLinkedModels();
			return isset($this->hierarchy_records[$link_id])?$this->hierarchy_records[$link_id]:null;
		}
	}
	/**
	 * Retrieve linked models
	 *
	 * @param ActiveRecord|null $current_model
	 *
	 * @return array
	 */
	public function getLinkedModels($current_model = null) {

		// If linked model were calculated, skip processing
		if (count($this->hierarchy_records)) {
			return $this->hierarchy_records;
		}

		if (!isset($this->hierarchy_links)) {
			return [];
		}

		// Load models for the filter links
		$this->hierarchy_records = [];
		foreach($this->hierarchy_links as $filter_variable => $filter_model) {
			// Check if a hierarchy filter was provided for linked model
			if (isset($this->hierarchy_filters[$filter_variable])) {
				// Add the record which matches the filter
				$this->hierarchy_records[$filter_variable] = $filter_model['model']::findOne($this->hierarchy_filters[$filter_variable]);
			// Check if linked model is available linked to main model
			} elseif ($current_model && $current_model->$filter_model['linked_model']) {
				// Add the linked record to main model
				$this->hierarchy_records[$filter_variable] = $current_model->$filter_model['linked_model'];
			}
		}
		return $this->hierarchy_records;
	}

	/**
	 * Add breadcrumbs to the view
	 *
	 * @param array $additional_breadcrumbs
	 */
	public function addBreadCrumbs($additional_breadcrumbs = []) {
		// Gather breadcrumbs for all linked models
		foreach($this->getLinkedModels() as $filter_variable => $model) {
			if (!isset($this->hierarchy_links[$filter_variable]['breadcrumbs']) || is_null($model)) {
				continue;
			}
			// For each hierarchy check if there is
			foreach($this->hierarchy_links[$filter_variable]['breadcrumbs'] as $breadcrumb_def) {
				// Only hierarchy filters with label are processed
				if (isset($breadcrumb_def['label'])) {
					// Setup breadcrumb label
					$label = $breadcrumb_def['label'];
					if ($label instanceof \Closure) {
						// Execute function
						$breadcrumb = [
							'label' => $label($model)
						];
					} elseif (isset($breadcrumb_def['name_field'])) {
						// Replace {model_name} in label with value
						$breadcrumb = [
							'label' => Yii::t(
								'app',
								$label,
								['model_name' => yii\helpers\ArrayHelper::getValue($model, $breadcrumb_def['name_field']) ]
							)
						];
					} else {
						$breadcrumb = [ 'label' => $label ];
					}

					// Setup breadcrumb url
					if (isset($breadcrumb_def['url'])) {
						// Add url to breadcrumb definition
						$breadcrumb['url'] = $breadcrumb_def['url'];

						// Find field references in url
						preg_match_all('/\{(.*?)\}/', $breadcrumb['url'], $replacements);
						foreach($replacements[0] as $key => $replacement) {
							if (count($replacement) > 0) {
								// Replace reference '{<fieldname>}' with value from model
								$breadcrumb['url'] = strtr($breadcrumb['url'],
									[$replacements[0][$key] => $model->{$replacements[1][$key]}]);
							}
						}
					}
					// Add to the view breadcrumbs array
					$this->view->params['breadcrumbs'][] = $breadcrumb;
				}
			}
		}
		// Add additional breadcrumbs to the end
		foreach($additional_breadcrumbs as $crumb) {
			$this->view->params['breadcrumbs'][] = $crumb;
		}
	}

	/**
	 * New model with default values from linked models
	 *
	 * @return ActiveRecord
	 */
	protected function newModel() {
		// Get model
		$model = parent::newModel();

		// Skip processing if no filter link models or links are available
		if ( !isset($this->hierarchy_links) || count($this->hierarchy_links) == 0 ) {
			return $model;
		}

		// Load models for the filter links
		foreach($this->hierarchy_links as $filter_variable => $filter_model) {
			if (isset($this->hierarchy_filters[$filter_variable]) && $model->isAttributeActive($filter_variable)) {
				$model->$filter_variable = $this->hierarchy_filters[$filter_variable];
			}
		}
		return $model;
	}
}