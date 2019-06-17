<?php
    namespace MP\Model;

    class Database extends \medoo{
        
        protected $cacheTime = 0;

        public function setCacheTime($cacheTime = 0){
            $this->cacheTime = $cacheTime;
        }        
        
        public function getPdo(){
            return $this->pdo;    
        }
        
        //Sobresceve
        public function cachedQuery($query, $container = null, $cacheTime = 0){
            if($cacheTime > 0 && !is_null($container)){
                $key = md5($query);
                $objCachedString = $container->cache->getItem($key);
                
                if (!$objCachedString->isHit()){
                    $resultSet = parent::query($query)->fetchAll(\PDO::FETCH_ASSOC);
                    $objCachedString->set($resultSet)->expiresAfter($cacheTime);//in seconds, also accepts Datetime 
                    $container->cache->save($objCachedString); // Save the cache item just like you do with doctrine and entities
                    return $resultSet;
                }else{
                    return $objCachedString->get();
                }
                
            }
            return parent::query($query);
        }
    }
