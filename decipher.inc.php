<?php


/** decipher_mysql class
 *
 */
class decipher_mysql extends decipher {
  
  function database_connect() {
  
    $link = mysql_connect("127.0.0.1", "root", "")
      or die("Keine Verbindung möglich: " . mysql_error());

    mysql_select_db("english_words") or die("Auswahl der Datenbank fehlgeschlagen");
    
  }
  
  //return TRUE OR FALSE;
  function database_search_word($word) {
    
    $query = "SELECT 1 FROM words where word = '" . strtolower($word) . "'";
    $result = mysql_query($query);
    if(mysql_num_rows($result) > 0) {
      return true;
    } else {
      return false;
    }
    
  }
   
}









/**
 * decipher class.
 */
abstract class decipher {
  
  var $testing_text;      //the text, we want to decipher
  var $text_sum;          //the total number of letters in the text
  var $words;             //exploting the testing text in words
  var $basis_frequency;   //the frequency of each letter in the base language
  var $letter_frequency;  //the frequency of each letter in the testing text
  var $already_known;    //letters, we already know
  
  /**
   * decipher function.
   * 
   * @access public
   * @param mixed $basis_frequency
   * @param mixed $testing_text
   * @param array $already_known (default: array())
   * @return void
   */
  function decipher($basis_frequency, $testing_text, $already_known = array()) {
    
    if(!isset($basis_frequency) || !isset($testing_text)) die("ERROR: YOU HAVE TO SET THE BASIS FREQUENCY AND THE TEXT TO TEST!");
    
    $this->testing_text     = ereg_replace("[^a-z ]", "", strtolower($testing_text));
    
    $this->words = array();
    $words = explode(" ", $this->testing_text);
    foreach($words as $word) {
      if(array_key_exists($word, $this->words))
        $this->words[$word]++;
      else
      $this->words[$word] = 1;
    }

    arsort($this->words);
    
    $this->testing_text     = str_replace(" ", "", $this->testing_text);
    $this->text_sum         = strlen($this->testing_text);
    $this->basis_frequency  = $basis_frequency;
    $this->already_known   = $already_known;
    
    //generate letter frequency
    $this->letter_frequency();
    
    //connect to database
    $this->database_connect();
    
    return true;
  }


  /**
   * start function.
   * 
   * @access public
   * @param int $offset (default: 0)
   * @param int $limit (default: 5)
   * @return void
   */
  public function start($offset=0, $limit=5) {
    
    //get posible matching-letters for each letter & reduce the words-array to the limited
    $matching = $this->posible_matches($offset, $limit);    
    
    //generate all variants by posible matching-letters. can return FALSE, if the performance-index is to high
    $variants = $this->generate_variants($matching);
    
    if($variants) {
      return $this->build_words($variants, $offset, $limit);
    }
    
    return FALSE;
    
  }
  
  
  
  
  /**
   * performance_index function.
   * 
   * @access private
   * @param mixed $matching
   * @return void
   */
  private function performance_index($matching) {
    
    //generate an index to check the performance
    $test_array = array();
    foreach($matching as $arrays) {
      foreach($arrays as $letter) {
        array_push($test_array, $letter);
      }
    }
  
    $performance_index_c1 = count($test_array);
    $performance_index_c1;
        
    $test_array = array_unique($test_array);
    
    $performance_index_c2 = count($test_array);
    $performance_index = $performance_index_c1 - $performance_index_c2;
    
    //if the index is to high, we need to limit the size-parameter
    return $performance_index;
    
  }
  
  
  
  /**
   * posible_matches function.
   * 
   * @access private
   * @param mixed $offset
   * @param mixed $limit
   * @return void
   */
  private function posible_matches($offset, $limit) {
    
    //all letters from offset to limit frequent words
    $letters = array();
    
    $words = array_keys($this->words);
    $this->words = array();
        
    for($i=$offset; $i<$offset+$limit; $i++) {  
      $word = $words[$i];
      array_push($this->words, $word);
      
      for($c=0; $c<strlen($word); $c++) {
      
        //remove all already knwon letters
        if(!in_array($word{$c}, array_keys($this->already_known)))
          array_push($letters, $word{$c});
      }
    
    }
  
    $letters = array_unique($letters);
    
    $matching = array();
    
    $tmp_f = $this->basis_frequency;
    $tmp_f_value = array_keys($this->basis_frequency);
    $tmp_lf = $this->letter_frequency;
    
    foreach($letters as $letter) {
      
      $letter_freq = $tmp_lf[$letter];
      $matching[$letter] = array();
      
      $counter = 0;
      
      //we walk through the basis_frequence, to get the best matching 3 Elements
      foreach($tmp_f as $b_letter => $freq) {
        
        //$tmp_f is orderd desc, so we need to check the first element we are greater
        if($letter_freq >= $freq) {
          
          array_push($matching[$letter], $b_letter);          
          //last element
          
          $tmp_c = $counter-1;
          for($i=0; $i<4; $i++) {
            if($tmp_c >= 0) {
              array_push($matching[$letter], $tmp_f_value[$tmp_c]);
              $tmp_c--;
            }
          }
          
          $tmp_c = $counter+1;
          for($i=0; $i<4; $i++) {
            if(array_key_exists($tmp_c, $tmp_f_value)) {
              array_push($matching[$letter], $tmp_f_value[$tmp_c]);
              $tmp_c++;
            }
          }

          break;
        }
        
        $counter++;
      }
      
    }
    
    //add already known replacements to the matching table
    foreach($this->already_known as $letter => $replace) {
      $matching[$letter] = array($replace);
    }

    return $matching;
    
  }
  
  
  /**
   * generate_variants function.
   * gets posible matches, and generates all variantes. invoces the recursive generate_variante-method
   *
   * @access private
   * @param mixed $machtes
   * @return void
   */
  private function generate_variants($matching) {
    
    //if the p_index is to high, we need to stop because of performance issues
    if($this->performance_index($matching) > 29) {
      return FALSE;
    }
    
  
    $matches = array_values($matching);
    $match_keys = array_keys($matching);
    
    $all_variants = array();
    $variante = array();
    
    $this->generate_variante($matches, $match_keys, $variante, 0, $all_variants);
    
    return $all_variants;
  }


  /**
   * generate_variante function.
   * recursive method to generate all variantes
   *
   * @access private
   * @param mixed $matches
   * @param mixed $match_keys
   * @param mixed $variante
   * @param mixed $index
   * @param mixed &$all_variants
   * @return void
   */
  private function generate_variante($matches, $match_keys, $variante, $index, &$all_variants) {
    
    
    
    $replacements = $matches[$index];
    $letter = $match_keys[$index];
    
    if(is_array($replacements)) {
      foreach($replacements as $replacement) {
        
        if(!in_array($replacement, $variante)) {
          $variante[$letter] = $replacement;
          
          
          
          if($index < count($match_keys)-1) {
            $this->generate_variante($matches, $match_keys, $variante, $index+1, $all_variants);
          } else {
            array_push($all_variants, $variante);
          }
          
        }
      }
    }
  
  }
  
  
  /**
   * build_words function.
   * build all posible words, and check for each replacement, if all of the generated words do realy exist in the base_language
   *
   * @access private
   * @param mixed $variants
   * @param mixed $offset
   * @param mixed $limit
   * @return void
   */
  private function build_words($variants, $offset, $limit) {
    
    $replacements = array();

    foreach($variants as $var) {
            
      $cur_words = array();
      
      for($i=0; $i<$limit; $i++) {
        
        $cur_word = $this->words[$i];
        
        foreach($var as $find => $replace) {
          $cur_word = str_replace($find, strtoupper($replace), $cur_word);
        }
        
        array_push($cur_words, $cur_word);
      }
      
      $hits = $this->does_this_words_exists($cur_words);
      //if all words match, we return this replacement
      if($hits == 1) {
        return $var;
      }
    }
    
    return false;
  }
  
  
  /**
   * does_this_words_exists function.
   * checks, if all of the words do exist
   * returns true, if all of them exist, otherwise an float-number representing the number of matches in percent
   *
   * @access private
   * @param mixed $words
   * @return void
   */
  private function does_this_words_exists($words) {
    
    $hits = 0;
    $count = count($words);
    
    foreach($words as $word) {
      $hits = $hits + (int)$this->database_search_word($word) / $count;
    }
    
    if($hits == 1) return TRUE;
    else return $hits;
  }


  /**
   * letter_frequency function.
   * 
   * @access private
   * @return void
   */
  private function letter_frequency() {

    $letter = array();

    //generate absolute frequency
    for($i=0; $i < $this->text_sum; $i++) {
      
      $cur_char = substr($this->testing_text, $i, 1);
      
      if(array_key_exists($cur_char, $letter)) {
        $letter[$cur_char]++;
      } else {
        $letter[$cur_char] = 1;
      } 
    }
    
    //sort the letter by frequency
    arsort($letter);
    
    
    //generate relative frequency
    foreach($letter as $l => $f) {
      $this->letter_frequency[$l] = $f / $this->text_sum * 100;
    }
    
  }
  

  /**
   * database_connect function.
   * connect to the database
   *
   * @access public
   * @abstract
   * @return void
   */
  abstract function database_connect();


  /**
   * database_search_word function.
   * query the word
   *
   * @access public
   * @abstract
   * @param mixed $word
   * @return void
   */
  abstract function database_search_word($word);
  
}