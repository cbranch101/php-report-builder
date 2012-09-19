<?php

	error_reporting(E_ALL); 
	ini_set( 'display_errors','1');

	class ChartBuilder {
		
		static $requiredParams = array(
			'x',
			'y',
			'labels',
		);
		
		static $requiredXAndY = array(
			'gather',
			'build',
			'label',
			'sort',
		);
		
		
		/**
		 * build function.
		 * 
		 * Convert the params array into a formatted chart
		 * 
		 * @access public
		 * @static
		 * @param array $params refer to read me for examples
		 * @return array
		 */
		static function build($params) {
			
			$yValues = $params['y_values'];
			$data = $params['data'];
			$labelledData = self::labelData($data, $params, $yValues);
			$labels = self::getLabels($labelledData);
			$groupedData = 
			self::groupData($labelledData, $labels);
			$reducedData = self::reduceData($groupedData, $params);
			$chart = self::fillOutData($reducedData, $labels, $yValues);
			return json_encode($chart);
			
		}
		
		/**
		 * fillOut function.
		 *
		 * If any of the supplied Y values didn't have any corresponding X values
		 * Add the associated labels to the chart with 0 values
		 * 
		 * @access public
		 * @static
		 * @param array $xReduced
		 * @param array $labels
		 * @param array $yInput
		 * @return array
		 */
		static function fillOutData($data, $labels, $yValues) {
			__::each($yValues, function($y) use(&$data, $labels) {
				if(!isset($data[$y])) {
					$emptyItem = array();
					__::each($labels, function($label) use(&$emptyItem){
						$emptyItem[$label] = 0;
					});
					$data[$y] = $emptyItem;
				} else {
					__::each($labels, function($label) use(&$data, $y) {
						if(!isset($data[$y][$label])) {
							$data[$y][$label] = 0;
						}
					});
				}
			});
			return $data;
		}
		
		
		/**
		 * labelX function.
		 *
		 * Iterate over x Input and apply labels
		 * 
		 * @access public
		 * @static
		 * @param array $xLabelled
		 * @param array $params
		 * @return array
		 */
		static function labelData($data, $params, $yValues) {
			$labelMap = isset($params['map_labels']) ? $params['map_labels']($data) : null;
			return __::chain($data)
				->map(function($item) use($params, $labelMap, $yValues) {
					$label = $params['get_label']($item);
					$label = ChartBuilder::mapLabel($label, $labelMap);
					ChartBuilder::confirmString($label);
					$y = $params['get_y']($item);
					if(in_array($y, $yValues)) {
						$item['label'] = $label;
						$item['y'] = $y;
						return $item;
					}
				})
				->compact()
			->value();
		}
		
		static function getLabels($labelledData) {
			return __::chain($labelledData)
				->pluck('label')
				->uniq()
			->value();
		}
		
		static function confirmString($label) {
			if(!is_string($label)) {
				throw new Exception("label $label must be a string");
			}
		}
		
		static function mapLabel($label, $labelMap = null) {
			if($labelMap) {
				return $labelMap[$label];				
			} else {
				return $label;
			}
		}
		
		/**
		 * reduceX function.
		 *
		 * Iterate over all of the grouped X data and reduce it
		 * to a single value
		 * 
		 * @access public
		 * @static
		 * @param mixed $xGrouped
		 * @param mixed $params
		 * @return void
		 */
		static function reduceData($data, $params) {
			return __::map($data, function($dataByLabelInY, $y) use($params) {
				return __::chain($dataByLabelInY)
					->map(function($dataInLabel, $label) use($params) {
						$reducedValue =  ChartBuilder::fancyReduce($dataInLabel, $params['reduce']['onFirst'], $params['reduce']['onNext']);
						return array($label => $reducedValue);		
					})
					->flatten(true)
				->value();
			});
		}	
							
		static function fancyReduce($array, $onFirst, $onNext) {
			return __::reduce($array, function($prev, $next) use($onFirst, $onNext){
				if($prev) {
					return call_user_func($onNext, $prev, $next);
				} else {
					return call_user_func($onFirst, $next);
				}
			});
		}
		
		static function groupData($data) {
			$dataGroupedByY = __::groupBy($data, 'y');
			$dataGroupedByYThenByLabel = __::map($dataGroupedByY, function($dataInY){
				return __::groupBy($dataInY, 'label');
			});
			return $dataGroupedByYThenByLabel;
		}		
		
	}
