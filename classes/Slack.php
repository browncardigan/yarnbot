<?php
	
class Slack {
	
	var $db = false;
	var $message_data = array();
	var $tags_data = array();
	var $css = array();
	var $test_mode = false; // don't send to slack
	
	function __construct() {
		// css config
		$this->css['color'] = '#d61b37';
	}
	
	function init() {
		// db connect
		@include(ROOT . "classes/Db.php");
		$this->db = new Db;
		$this->db->connect();
	}
	
	function resetData() {
		$this->message_data = array();
		$this->tags_data = array();
	}
	
	function sendWeeklyUpdate() {
		
		// new test alert (temp)
		$this->message_data['text'] = "~~~~~~~~~~ INCOMING TEST ALERT ~~~~~~~~~~\n";
		$this->sendRequest();
		$this->resetData(); 
		
		// get all the intervals we want to display
		$intervals = $this->db->results("select * from yarnbot_intervals");
	
		foreach ($intervals as $i) {
		
			$date = date("Y-m-d", strtotime($i['strtotime_value']));
		
			$query = "SELECT yp.*, p.post_title, p.post_date, t.term_id, t.name, t.slug, tt.taxonomy 
			FROM yarnbot_posts yp, wp_posts p, wp_terms t, wp_term_taxonomy tt, wp_term_relationships tr
			WHERE tr.term_taxonomy_id=tt.term_taxonomy_id
			AND tt.term_id=t.term_id
			AND tr.object_id=yp.post_id 
			AND yp.post_id=p.ID 
			AND tt.taxonomy='post_tag' 
			AND p.post_date < '" . date("Y-m-d", strtotime("+1 day", strtotime($date))) . "' 
			AND p.post_date > '" . date("Y-m-d", strtotime("-6 days", strtotime($date))) . "'";
			
			// weekly report, get articles
			if ($i['interval_name'] == 'one week') {
				$query .= " ORDER BY yp.ga_sessions_firstweek DESC";
			}
			else {
				// monthly, 6 monthly etc --- filter to exclude music
				$query .= " AND p.ID IN (
					SELECT distinct(p.ID) 
					FROM wp_posts p, wp_terms t, wp_term_taxonomy tt, wp_term_relationships tr
					WHERE tr.term_taxonomy_id=tt.term_taxonomy_id
					AND tt.term_id=t.term_id 
					AND tr.object_id=p.ID 
					AND t.name NOT LIKE 'music' 
					AND tt.taxonomy LIKE 'category' 
					GROUP BY p.ID
				)";
			}
			
			$this->tags_data = $this->db->results($query, false);

			if ($i['interval_name'] == 'one week') {
				$this->buildReportWeekly();
			}
			else {
				$this->buildTopicReport($i['report_intro']);
			}
			
			$res = $this->sendRequest();
			
			$this->resetData();
			
			echo "results for " . $i['interval_name'] . ": " . $res . "\n";
	
		}
		
	}
	
	function endOfMessage() {
		$this->message_data = array(
			'text' 			=> 'Edit Message Settings',
			'attachments'	=> array()
		);
		
		$this->message_data['attachments'][] = array(
			'fallback'	=> 'Watch this topic at: [someurl]',
			'actions'	=> array(
				array(
					'type' 	=> 'button',
					'text'	=> 'YES',
					'url'	=> 'http://temp.jonnynail.com/yarnbot/hello.php?button',
					'style'	=> 'primary'
				)
			)
		);
		$this->debug($this->message_data);
		//$this->message_data = json_decode(file_get_contents(ROOT . "temp/button.json"), true);
		//$this->debug($this->message_data);
		//exit;
		$this->sendRequest();
	}
	
	function buildReportWeekly() {
		$this->message_data = array('text' => "Hey team! \n\n*Last week's most popular articles* were:");
		$weekly = array();
		foreach ($this->tags_data as $t) {
			$key = $t['post_id'];
			if (!isset($weekly[$key])) {
				$weekly[$key] = $t;
				$weekly[$key]['tags'] = array($t['name']);
			}
			else {
				$weekly[$key]['tags'][] = $t['name'];
			}
		}
		$weekly = array_slice($weekly, 0, 3);
		foreach ($weekly as $w) {
			$this->message_data['attachments'][] = array(
				'color'			=>	$this->css['color'],
				'title'			=>	$w['post_title'],
				'title_link'	=>	'http://junkee.com' . $w['og_url'],
				'fields'		=> array(
					array(
						'title' => 'Page Views',
						'value'	=> $w['ga_sessions_firstweek'],
						'short'	=> true
					),
					array(
						'title' => 'Tags',
						'value'	=> implode(", ", $w['tags']),
						'short'	=> true
					)
				)
			);
		}
	}
	
	function buildTopicReport($interval_intro=false) {
		
		$this->message_data = array('text' => "Hey team! \n\n");
		
		if ($interval_intro) {
			$this->message_data['text'] .= $interval_intro . "\n\n";
		}
		$tags = array();
		
		// format data
		foreach ($this->tags_data as $t) {
			$tags_key = $t['name'];
			if (!isset($tags[$tags_key])) {
				$tags[$tags_key] = array('impressions' => 0, 'slug' => $t['slug'], 'articles' => array());
			}
			$tags[$tags_key]['impressions'] += $t['ga_sessions_firstweek'];
			$tags[$tags_key]['articles'][] = array(
				'impressions'	=> $t['ga_sessions_firstweek'],
				'title'			=> $t['post_title'],
				'url'			=> 'http://junkee.com' . $t['og_url']
			);
		}
		
		arsort($tags);
		
		// group multiple tags for single articles together
		$tags2 = array();
		$current_key = 0;
		$prev_impressions = 0;
		foreach ($tags as $tag_name => $tag) {
			if ($tag['impressions'] != $prev_impressions) {
				$current_key++;
			}
			if (!isset($tags2[$current_key])) { $tags2[$current_key] = $tag; }
			if (!isset($tags2[$current_key]['tag'])) { $tags2[$current_key]['tag'] = array(); }
			$tags2[$current_key]['tag'][] = $tag_name;
			$prev_impressions = $tag['impressions'];
		}
		
		// top five only
		$tags2 = array_slice($tags2, 0, 5);
	
		// print
		$this->message_data['attachments'] = array();
		
		foreach ($tags2 as $list_number => $t) {
			
			arsort($t['articles']);
			$top_article = current($t['articles']);
			
			// format tag/s nicely - ie. Kanye West and Walmart
			$tag_name = '';
			for ($i=0; $i<count($t['tag']); $i++) {
				if (count($t['tag']) > 1) {
					if ($i+1 == count($t['tag'])) {
						$tag_name .= " and ";
					}
					else if ($i > 0) { $tag_name .= ", "; }
				}
				$tag_name .= $t['tag'][$i];
			}
			
			$tag_title = ($list_number+1) . ". " . $tag_name;
			
			$this->message_data['attachments'][] = array(
				'color'			=>	$this->css['color'],
				'title'			=>	$tag_title,
				'title_link'	=>	'http://junkee.com/tag/' . $t['slug'],
				'text'			=> "Number of articles: " . count($t['articles']) . " | Top Post: " . $top_article['title'],
				'fallback'	=> 'Watch this topic at: [someurl]',
				/*
				'actions'	=> array(
					array(
						'type' 	=> 'button',
						'text'	=> 'Watch Topic/s',
						'url'	=> 'http://temp.jonnynail.com/yarnbot/hello.php?topic=' . implode(",",$t['tag'])
					)
				)
				*/
			);

		}
		
	}
	
	function commandReply($command=false, $data=false) {
		
		$response = false;
		
		if ($command) {
			
			switch ($command) {
				
				case 'topic':
				$response = "Ok, we'll remind you about *" . $data['topic'] . "*";
				break;
			
				case 'invalid':
				$response = "Sorry, I don't understand that request...";
				break;
				
			}
			
		}
		
		if ($response) {
			echo $response;
			exit;
			//$this->message_data = array('text' => $response);
			//return $this->sendRequest();
		}
		
	}
	
	function tester($msg=false) {
		if (!$msg) { $msg = "helloworld"; }
		$this->message_data = array('text' => $msg);
		//$this->message_data['text'] = implode("---", $_REQUEST);
		return $this->sendRequest();
	}
	
	function sendRequest() {
		
		if ($this->test_mode) {
			print_r($this->message_data);
			return "test mode";
		}
		
		else {
			$data_json = json_encode($this->message_data);
			$ch = curl_init(SLACK_WEBHOOK_URL);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$result = curl_exec($ch);
			curl_close($ch);
			return $result;
		}
		
	}
	
	function debug($a) {
		echo '<pre>';
		print_r($a);
		echo '</pre>';
	}
	
}
	
?>