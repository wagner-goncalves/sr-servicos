<?php
	namespace MP\Notifications\Model; 
    
    class Notification
    {
		/*
				'headings' => [
					'en' => 'Oi, fofinha'
				],
				'contents' => [
					'en' => 'Amo vc'
				],
                'include_player_ids' => ['b77f8d23-3e6e-4587-ae96-8d195a57a68a'],
				//'included_segments' => ['All'],
				'data' => ["oidUsuario" => "1", "oidNotificacao" => "1"],
				'buttons' => [
					["id" => "id1", "text" => "button1", "icon" => "ic_menu_share"], 
					["id" => "id2", "text" => "button2", "icon" => "ic_menu_send"]
				],
				'small_icon' => 'ic_stat_sr_cidadao',
				'large_icon' => 'http://www.srcidadao.com.br/servicos/public/politicos/101309.jpg',
				'android_led_color' => '00FF00FF',
				'android_group' => 'SrCidadao',
				'android_group_message' => ["en" => "$[notif_count] alertas"]		
		*/
		public function setHeadings($headings){
			$this->headings = $headings;
		}
		
		public function setContents($headings){
			$this->contents = $contents;
		}		
 
    }
    