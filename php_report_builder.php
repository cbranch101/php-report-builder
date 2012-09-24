<?php

	error_reporting(E_ALL); 
	ini_set( 'display_errors','1');

	class ChartBuilder {
		
		static $requiredParams = array(
			'y_values',
			'get_label',
			'reduce',
			'get_y',
			'data',
		);
		
		static $requiredReduce = array(
			'on_first',
			'on_next',
		);
		
		static $reduceMessages = array(
			'on_first' => 'Please supply a function to perform on the first item in a reduce',
			'on_next' => 'Please supply a function to perform on all other items after the first in a reduce',
		);
		
		static $requiredMessages = array(
			'y_values' => 'Please supply the Y values you want included in the chart',
			'get_label' => 'Please supply a function to get the label for a row of data',
			'reduce' => 'Please supply and array of functions to specify how to reduce a row of data',
			'get_y' => 'Please supply a function to get a Y value of an a row of data',
			'data' => 'Please supply the data you would like to build the chart out of',
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
			self::confirmParams($params);
			$yValues = $params['y_values'];
			$data = $params['data'];
			$labelledData = self::labelData($data, $params, $yValues);
			$labels = self::getLabels($labelledData);
			$groupedData = 
			self::groupData($labelledData, $labels);
			$reducedData = self::reduceData($groupedData, $params);
			$chart = self::fillOutData($reducedData, $labels, $yValues);
			return $chart;
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
		
		/**
		 * getLabels function.
		 * 
		 * @access public
		 * @static
		 * @param array $labelledData
		 * @return array
		 */
		static function getLabels($labelledData) {
			return __::chain($labelledData)
				->pluck('label')
				->uniq()
			->value();
		}
		
		/**
		 * confirmString function.
		 * 
		 * @access public
		 * @static
		 * @param string $label
		 * @return boolean
		 */
		static function confirmString($label) {
			if(!is_string($label)) {
				throw new Exception("label $label must be a string");
			}
		}
		
		/**
		 * mapLabel function.
		 *
		 * Map the values supplied in the data to a given label
		 * 
		 * @access public
		 * @static
		 * @param string $label
		 * @param array $labelMap (default: null)
		 * @return string
		 */
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
						$reducedValue =  ChartBuilder::fancyReduce($dataInLabel, $params['reduce']['on_first'], $params['reduce']['on_next']);
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
		
		static function confirmParams($params) {
			__::each(self::$requiredParams, function($requiredParam) use($params){
				if(!isset($params[$requiredParam])) {
					$output = json_encode($params);
					$message = "Param '$requiredParam' is missing from $output.";
					$message .= " " . ChartBuilder::$requiredMessages[$requiredParam];
					throw new Exception($message);
				}
			});
			__::each(self::$requiredReduce, function($reduceFunction) use($params){
				if(!isset($params['reduce'][$reduceFunction])) {
					$output = json_encode($params);
					$message = "Param '$reduceFunction' in 'reduce' is missing from $output.";
					$message .= " " . ChartBuilder::$reduceMessages[$reduceFunction];
					throw new Exception($message);
				}
			});
		}		
		
	}
