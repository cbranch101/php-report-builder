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
			$yInput = $params['y']['gather']();
			$xInput = $params['x']['gather']();
			$xLabelled = self::labelX($xInput, $params);
			return json_encode($xLabelled);
		}
		
		/**
		 * labelX function.
		 *
		 * Iterate over x Input and apply labels
		 * 
		 * @access public
		 * @static
		 * @param mixed $xLabelled
		 * @param mixed $params
		 * @return void
		 */
		static function labelX($xLabelled, $params) {
			return __::map($xLabelled, function($xItem) use($params) {
				$xLabel = $params['x']['label']($xItem);
				$xItem['label'] = $xLabel;
				return $xItem;
			});
		}
				
		static function sumByKey($keys, $array) {
			return A5_Toolkit::fancyReduce(
			$array, 
			function($previousSums) use($keys, $array) {
				return A5_Toolkit::arrayKeyTransform($previousSums, $keys, function($value){
					return $value ? $value : 0;
				});
			},
			function($previousSums, $nextItem) use($keys) {
				return A5_Toolkit::arrayKeyTransform($previousSums, $keys, function($valueToSum, $key) use($nextItem){
					$previous = isset($nextItem[$key]) ? $nextItem[$key] : 0;
					return $previous + $valueToSum;
				});
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
		
		static function arrayKeyTransform($array, $keys, $transformFunction) {
			$transformedArray = array();
			__::each($keys, function($key) use($array, &$transformedArray, $transformFunction) {
				$value = isset($array[$key]) ? $array[$key] : null;
				$transformedArray[$key] = call_user_func($transformFunction, $value, $key);
			});
			return $transformedArray;
		}
		
		
	}
