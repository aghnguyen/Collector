<?php
/*  Collector
    A program for running experiments on the web
    Copyright 2012-2014 Mikey Garcia & Nate Kornell
 */


	#### #### CUSTOM FUNCTIONS #### ####


	#### Write array to a line of a tab delimited text file
	function arrayToLine ($row, $fileName, $d = NULL) {
		if ($d === NULL) {
			$d = isset ($_SESSION['OutputDelimiter']) ? $_SESSION['OutputDelimiter'] : "\t";
		}
		if (!is_dir(dirname($fileName))) {
			mkdir(dirname($fileName), 0777, true);
		}
		foreach ($row as &$datum) {
			$datum = str_replace(array("\r\n", "\n", "\t", "\r", chr(10), chr(13)),  ' ',  $datum);
		}
		unset($datum);
		$fileTrue = fileExists($fileName);
		if (!$fileTrue) {
			$file = fopen($fileName, "w");
			fputcsv($file, array_keys($row), $d);
			fputcsv($file, $row, $d);
		} else {
			$file = fopen($fileTrue, "r+");
			$headers = array_flip(fgetcsv($file, 0, $d));
			$newHeaders = array_diff_key($row, $headers);
			if ($newHeaders !== array()) {
				$headers = $headers+$newHeaders;
				$oldData = stream_get_contents($file);
				rewind($file);
				fputcsv($file, array_keys($headers), $d);
				fwrite($file, $oldData);
			}
			fseek($file, 0, SEEK_END);
			$row = SortArrayLikeArray($row, $headers);
			fputcsv($file, $row, $d);
		}
		fclose($file);
		return $row;
	}



    #### Code that block shuffles an array.  Give it an $input array and the key for the grouping factor.
    function BlockShuffle ($input, $groupingFactor) {
        $outputArray = array();

        // Use this logic when second-order shuffling is present
        if (array_key_exists($groupingFactor . '2', $input[2])) {
            // creates a hierarchical structure of higher order blocks which contain lower order blocks which contain specific items
            $holder  = array();
            $HiCount = 0;
            $LoCount = 0;
            $holder[$HiCount][$LoCount][] = $input[0];                                      // load initial item into first pos
            for ($arrayPos = 0; $arrayPos < count($input); $arrayPos++) {
                $CurrentLine = $input[$arrayPos];
                if (isset($input[$arrayPos+1]) == FALSE) {                                  // check that there is a next line
                    continue;
                } else {                                                                    // save nextline for later inserting
                    $NextLine = $input[$arrayPos+1];
                }
                if ($CurrentLine[$groupingFactor . '2'] == $NextLine[$groupingFactor . '2']) {
                    if ($CurrentLine[$groupingFactor] == $NextLine[$groupingFactor]) {
                        $holder[$HiCount][$LoCount][] = $NextLine;
                        continue;
                    } else {
                        $LoCount++;
                        $holder[$HiCount][$LoCount][] = $NextLine;
                        continue;
                    }
                } elseif ($CurrentLine[$groupingFactor . '2'] != $NextLine[$groupingFactor . '2']) {
                    $HiCount++;
                    $LoCount = 0;
                    $holder[$HiCount][$LoCount][] = $NextLine;
                    continue;
                }
            }            // runs through the hierarchical structure and shuffles where applicable
            for ($hi=0; $hi < count($holder); $hi++) {
                if (trim(strtolower($holder[$hi][0][0][$groupingFactor . '2'])) != 'off') {
                    shuffle($holder[$hi]);
                }
                for ($lo=0; $lo < count($holder[$hi]) ; $lo++) {
                    if (trim(strtolower($holder[$hi][$lo][0][$groupingFactor])) != 'off') {
                        shuffle($holder[$hi][$lo]);
                    }
                }
            }
            // items are now higher and lower order shuffled so simply place them into outputArray
            foreach ($holder as $outer) {
                foreach ($outer as $inner) {
                    foreach ($inner as $item) {
                        $outputArray[] = $item;                                             // put the item into the next available output position
                    }
                }
            }
            return $outputArray;
        }
        // Use this logic when second order shuffling is NOT present
        else {
            /*  load items into array that groups as blocks
                then as items within blocks. e.g., $temp[$blockNum][#]
             */ 
            $temp = array();
            $blockNum = 0;
            $temp[$blockNum][] = $input[0];                                         // start by loading initial item into temp
            for ($pos=0; $pos < count($input); $pos++) {                            // go through all items
                $currentLine = $input[$pos];                                            // set currentLine for comparison
                if(isset($input[$pos+1])) {                                             // if there is another line to add
                    $nextLine = $input[$pos+1];                                             // grab it
                } else {    continue;    }                                              // or stop loading
                if($currentLine[$groupingFactor ] !== $nextLine[$groupingFactor]) {     // if the nextline uses a different shuffle then change blockNum
                    $blockNum++;
                }
                $temp[$blockNum][] = $nextLine;                                         // loading nextLine into the correct $temp block of items
            }
            // shuffle appropriate blocks then load into output
            foreach ($temp as $group) {
                if(trim(strtolower($group[0][$groupingFactor])) != 'off') {
                    shuffle($group);
                }
                foreach ($group as $line) {
                    $outputArray[] = $line;
                }
            }
            return $outputArray;
        }
    }



    /**
     *  arrayCleaner
     *
     *  Barebones function to prevent passing code along
     *  This works with nested arrays
     *
     *  Add any other cleaning functions you want to it
     */
    function arrayCleaner($cleanarr) {
        if (is_array($cleanarr)) {
            return(array_map('arrayCleaner', $cleanarr));
        } else {
          return htmlspecialchars($cleanarr, ENT_QUOTES);
        }
    }

	
	
	function camelCase($str) {
		$str = ucwords(strtolower(trim($str)));
		$str = str_replace(' ', '', $str);
		$str[0] = strtolower($str[0]);
		return $str;
	}
	
	
	#### finding column entires specific to a given $postNumber (e.g., Post 1, Post 2, Post 3)
	function ExtractTrial($procedureRow, $postNumber) {
		$output = array();
		if ($postNumber < 1) {
			foreach ($procedureRow as $column => $value) {
				if (substr(strtolower(trim($column)), 0, 4 ) === 'post') continue;
				$output[trim($column)] = $value;
			}
		} elseif ($postNumber == 1) {
			foreach ($procedureRow as $column => $value) {
				$col = trim($column);
				if (strtolower(substr($col, 0, 4)) === 'post') {
					$col = trim(substr($col, 4));
					if (is_numeric($col[0]) AND $col[0] !== '1') continue;
					if ($col[0] === '1' AND is_numeric($col[1])) continue; // in case of post-trial 12...
					if ($col[0] === '1') $col = trim(substr($col, 1));
					$output[$col] = $value;
				}
			}
		} else {
			foreach ($procedureRow as $column => $value) {
				$col = trim($column);
				if (strtolower(substr($col, 0, 4)) === 'post') {
					$col = trim(substr($col, 4));
					if (substr($col, 0, strlen($postNumber)) == $postNumber) {
						$col = substr($col, strlen($postNumber));
						if (is_numeric($col[0])) continue;
						$output[trim($col)] = $value;
					}
				}
			}
		}
		
		foreach ($output as $column => $value) {
			$name = $column;
			if (is_numeric($column[0])) {
				$name = '_' . $column;
			}
			$name = preg_replace('/[A-Z]/', ' \\0', $column);
			$name = camelCase($name);
			$name = preg_replace('/[^0-9a-zA-Z_]/', '', $name);
			if ($name === 'trial' OR $name === 'trialtype') $name = 'trialType';
			global $$name;
			if (isset($$name)) continue;	// aliases won't overwrite existing variables
			$$name = $value;
		}
		return $output;
	}
	
	
	
	#### function that converts smart quotes, em dashes, and u's with umlats so they display properly on web browsers
	function fixBadChars ($string) {
		// Function from http://shiflett.org/blog/2005/oct/convert-smart-quotes-with-php
		// added chr(252) 'lowercase u with umlat'
		$search = array(chr(145),
						chr(146),
						chr(147),
						chr(148),
						chr(151),
						chr(252)  );

		$replace = array("'",
						 "'",
						 '"',
						 '"',
						 '-',
						 '&uuml;' );
		return str_replace($search, $replace, $string);
	}



    #### custom function to read from tab delimited data files;  pos 0 & 1 are blank,  header names are array keys
    function GetFromFile ($fileLoc, $padding = TRUE, $delimiter = "\t") {
        $file = fopen($fileLoc, 'r');                       // open the file passed through the function arguement
        $keys = fgetcsv($file, 0, $delimiter);              // pulling header data from top row of file
        if ($padding == TRUE) {
            $out = array(0 => 0, 1 => 0);                       // leave positions 0 and 1 blank (so when I call $array[#] it will corespond to the row in excel)
        }
        while ($line = fgetcsv($file, 0, $delimiter)) {     // capture each remaining line from the file
            while (count($keys) > count($line)) {               // make sure each line has the right # of columns
                $line[] = '';
            }
            $tOut = array_combine($keys, $line);            // combine the line of data with the header
            if (isBlankLine($tOut)) {                        // do not include blank lines in output
                continue;
            }
            $out[] = $tOut;                                 // add this combined header<->line array to the ouput array
        }
        fclose($file);
        foreach ($out as &$row) {                           // trim all cells and encode using utf8 character set
            foreach ($row as &$cell) {
                $cell = trim(utf8_encode($cell));
            }
        }
        return $out;
    }



    function initiateCollector() {
        ini_set('auto_detect_line_endings', true);              // fixes problems reading files saved on mac
        session_start();                                        // start the session at the top of each page
        if (!isset($_SESSION['Debug']) OR $_SESSION['Debug'] == FALSE) {        // disable error reporting during debug
            error_reporting(0);
        }
    }



    #### function that returns TRUE or FALSE if a string is found in another string
    #### similar to stripos()
    function inString ($needle, $haystack, $caseSensitive = FALSE) {
        if ($caseSensitive == FALSE) {
            $haystack = strtolower($haystack);
            $needle   = strtolower($needle);
        }
        if (strpos($haystack, $needle) !== FALSE) {
            return TRUE;
        } else { return FALSE; }
    }



    #### if an array is empty, all positions == "", return TRUE
    function isBlankLine ($array) {
        foreach ($array as $item) {
            if ($item != "") {
                return FALSE;
            }
        }
        return TRUE;
    }


    #### checking if a key exists within a GetFromFile array;  returns TRUE/FALSE
    function keyCheck ($array, $key, $errorArray, $searched) {
        foreach ($array as $line) {
            if ($line == 0) {
                continue;
            } else {
                if (array_key_exists($key, $line) == TRUE) {
                    return $errorArray;
                } else {
                    $errorArray['Count']++;
                    $errorArray['Details'][] = 'Did not find required column <b>' . $key . '</b> within ' . $searched;
                    return $errorArray;
                }
            }
        }
        return $errorArray;
    }
    
    
    #### I plan on making a function that creates the trials in login.php 
    function makeTrial () {
        global $procedure;
        global $stimuli;
        global $allKeysNeeded;
                
        $trial = array();
        
        return $trial;
        
        /*
         * Ideas:
         *     Accept a range of stimuli instead of just single stimuli (implode by pipes)
         *     Automatically fill 0 stim items with 'n/a'
         */
    }


    #### takes an input ($info) array and merges it into a target array ($place).  Optional, prepend all $info keys with a $keyMod string
    function placeData ($data, $place, $keyMod = '') {
        foreach ($data as $key => $val) {
            $place[$keyMod.$key] = $val;
        }
        return $place;
    }


    #### Debug function I use to display arrays in an easy to read fashion
    function readable ($displayArray, $name = "Untitled array") {
        // convert to string to prevent parsing code
        $clean_displayArray = arrayCleaner($displayArray);

        echo '<div>';
        echo     '<div class="button collapsibleTitle">
                      <h3>'.$name.'</h3>
                      <p>(click to open/close)</p>
                  </div>';
        echo     '<pre>';
                      print_r($clean_displayArray);
        echo     '</pre>';
        echo '</div>';
    }
	
	
	function RemoveLabel ($input, $label, $extendLabel = TRUE) {
		$trimInput = trim(strtolower($input));
		$trimLabel = trim(strtolower($label)); 
		$labelLength = strlen($trimLabel);
		if (substr($trimInput, 0, $labelLength) === $trimLabel) {
			if ($extendLabel === TRUE) {		//if (my misguided efforts to make things for user-forgiving) end up breaking things, turn this off
				if (substr($trimInput, $labelLength, 1) === 's' ) { $labelLength++;}							//so we can put "labels" in the argument rather than just "label"
				if (substr($trimInput, $labelLength, 1) === ':' OR substr($trimInput, $labelLength, 1) === '=' ) { $labelLength++;} 	//more nice formatting is allowed in the excel file.  labels: yadayada or labels= yadayada
			}
			$output = trim(substr(trim($input), $labelLength));
		}
		else { $output = $input; }
		
		return $output;
	}


    #### add html tags for images and audio files but do nothing to everything else
    function show ($string) {
        if (!inString('www.', $string)) {                           // navigate path to Experiment folder (unless linking to external image)
            $fileName = '../Experiment/' . $string;
            if (FileExists($fileName)) {
                $fileName = FileExists($fileName);
            }
        }
        $stringLower = strtolower($fileName);                       // make lowercase version of input
        $findJPG     = strpos($stringLower, '.jpg');                // look for file extensions in the input
        $findGIF     = strpos($stringLower, '.gif');
        $findPNG     = strpos($stringLower, '.png');
        $findMP3     = strpos($stringLower, '.mp3');
        $findOGG     = strpos($stringLower, '.ogg');
        $findWAV     = strpos($stringLower, '.wav');


        if ($findGIF == TRUE || $findJPG == TRUE || $findPNG == TRUE) {             // if I found an image file extension, add html image tags
            $string = '<img src="' . $fileName . '">';
        } elseif ($findMP3 == TRUE || $findOGG == TRUE || $findWAV == TRUE) {       // if I found an audio file extension, add pre-cache code
            $string = '<source src="' . $fileName . '"/>';
        } else {
            // leave input as-is if no audio or image extensions are found
        }
        return $string;
    }



    function SortByKey ($input, $key) {
        $sorter = array();                                  // declare holding array
        for ($i = 0; $i < count($input); $i++) {            // load $input sorting key into $sorter
            $sorter[] = $input[$i][$key];
        }
        array_multisort($sorter, $input);                   // sort by $key value of each condition
        return $input;
    }


    #### function to determine which timing to apply to the current trial
    function trialTiming() {
        global $formClass;
        global $time;
        global $minTime;
        global $compTime;
        global $timingReported;
        global $_SESSION;
        global $debugTime;

        // determine which timing value to use
        if (is_numeric($timingReported)) {              // use manually set time if possible
            $time = $timingReported;
        } elseif ($timingReported != 'computer') {      // if not manual or computer then timing is user
            $time = 'user';
        } elseif (isset($compTime)) {                   // if a $compTime is set then use that
            $time = $compTime;
        } else { $time = 5; }                           // default compTime if none is set
        
        // override time in debug mode, use standard timing if no debug time is set
        if ($_SESSION['Debug'] == TRUE && $debugTime != '') {
            $time = $debugTime;
        }
        
        // set class for input form (shows or hides 'submit' button)
        if ($time == 'user') {
            $formClass = 'UserTiming';
        } else {
            $formClass = 'ComputerTiming';
        }
    }



	function FileExists( $filePath, $altExtensions = TRUE, $findDirectories = TRUE ) {
		if (is_file($filePath)) { return $filePath; }
		if (is_dir($filePath) AND $findDirectories) {
			if (substr($filePath, -1) === '/' ) {
				$filePath = substr($filePath, 0, -1);
			}
			return $filePath;
		}
		if ($filePath === '') { return FALSE; }
		$path_parts = pathinfo($filePath);
		$fileName = $path_parts['basename'];
		if (is_dir($path_parts['dirname'])) {
			$dir = $path_parts['dirname'];
			$pre = ($dir === '.' AND $filePath[0] !== '.') ? 2 : 0;
		} else {
			$dirs = explode('/', $path_parts['dirname']);
			if (is_dir($dirs[0])) {
				$dir = array_shift($dirs);
				$pre = 0;
			} else {
				$dir = '.';
				$pre = 2;
			}
			foreach ($dirs as $dirPart) {
				if (is_dir($dir . '/' . $dirPart)) {
					$dir .= '/' . $dirPart;
					continue;
				} else {
					$scan = scandir($dir);
					foreach ($scan as $entry) {
						if (strtolower($entry) === strtolower($dirPart)) {
							$dir .= '/' . $entry;
							continue 2;
						}
					}
					return FALSE;
				}
			}
			if (is_file($dir . '/' . $fileName)) { return substr($dir . '/' . $fileName,  $pre); }
			if (is_dir($dir . '/' . $fileName) AND $findDirectories) { return substr($dir . '/' . $fileName,  $pre); }
		}
		$scan = scandir($dir);
		$lowerFile = strtolower($fileName);
		foreach ($scan as $entry) {
			if (strtolower($entry) === $lowerFile) {
				if (is_dir($dir . '/' . $entry) AND !$findDirectories) { continue; }
				return substr($dir . '/' . $entry,  $pre);
			}
		}
		if ($altExtensions) {
			$baseFileName = strtolower($path_parts['filename']);
			foreach ($scan as $entry) {
				if ($entry === '.' OR $entry === '..') { continue; }
				if (is_dir($dir . '/' . $entry) AND !$findDirectories) { continue; }
				if (strpos($entry, '.') === FALSE ) {
					$entryName = strtolower($entry);
				} else {
					$entryName = strtolower(substr($entry, 0, strpos($entry, '.')));
				}
				if ($entryName === $baseFileName) {
					return substr($dir . '/' . $entry, $pre);
				}
			}
		}
		return FALSE;
	}
	
	function ComputeString ($template, $fileData = array()) {
		if ($fileData === array() AND isset($_SESSION)) {
			$fileData = $_SESSION;
		}
		foreach ($fileData as $key => $value) {
			$fileData[strtolower($key)] = $value;							    // so that $username will be found in $fileData['Username']
		}
		$templateParts = explode('_', $template);
		$outputParts = array();
		foreach ($templateParts as $part) {
			if (strpos($part, '$') === FALSE) {
				$outputParts[] = $part;
			} else {
				$str = substr($part, 0, strpos($part, '$'));                    // e.g., from 'Sess$Session', get 'Sess'
				$var = substr($part, strpos($part, '$')+1); 					// e.g., from 'Sess$Session', get 'Session'
				if (strpos($var, '[') === FALSE) {
					if (isset($fileData[$var]) AND is_scalar($fileData[$var])) {
						$str .= $fileData[$var];
					} else {
						$str .= '$' . $var;										// return the '$' so that it is obvious that a variable was searched for and not found
					}
				} else {														// if they want $_SESSION['Condition']['Condition Description'], we need to search index by index
					$key = substr($var, 0, strpos($var, '['));
					$indices = explode(']', substr($var, strpos($var, '[')));
					if (isset($fileData[$key])) {
						$val = $fileData[$key];
						foreach ($indices as $i) {
							if (strlen($i) === 0) { continue; }
							if ($i[0] !== '[') { continue; }
							if (isset($val[ substr($i, strpos($i,'[')+1)  ])) {
								$val = $val[ substr($i, strpos($i,'[')+1) ];
							} else {
								$val = NULL;
								break;
							}
						}
						if (is_scalar($val)) {
							$str .= $val;
						} else {
							$str .= '$' . $var;
						}
					} else {
						$str .= '$' . $var;										// return the '$' so that it is obvious that a variable was searched for and not found
					}
				}
				$outputParts[] = $str;
			}
		}
		$outputParts = implode('_', $outputParts);
		return $outputParts;
	}
	
	function rand_string ($length = 10) {
		$chars = "abcdefghijklmnopqrstuvwxyz0123456789";	

		$size = strlen($chars);
		$str = '';
		for ($i = 0; $i < $length; $i++) {
			$str .= $chars[rand(0, $size-1)];
		}

		return $str;
	}
	
	function AddPrefixToArray ($pre, $arr) {
		$out = array();
		foreach ($arr as $key => $val) {
			$out[$pre.$key] = $val;
		}
		return $out;
	}
	
	function SortArrayLikeArray ($arr, $template) {
		$out = array();
		foreach ($template as $key => $val) {
			$out[$key] = isset($arr[$key]) ? $arr[$key] : '';
		}
		return $out;
	}

?>