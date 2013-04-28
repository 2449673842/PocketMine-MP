<?php

/*

           -
         /   \
      /         \
   /   PocketMine  \
/          MP         \
|\     @shoghicp     /|
|.   \           /   .|
| ..     \   /     .. |
|    ..    |    ..    |
|       .. | ..       |
\          |          /
   \       |       /
      \    |    /
         \ | /

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.


*/

class LevelImport{
	private $path;
	public function __construct($path){
		$this->path = $path;
	}
	
	public function import(){
		if(file_exists($this->path."tileEntities.dat")){ //OldPM
			$level = unserialize(file_get_contents($this->path."level.dat"));
			console("[INFO] Importing OldPM level \"".$level["LevelName"]."\" to PMF format");
			$entities = new Config($this->path."entities.yml", CONFIG_YAML, unserialize(file_get_contents($this->path."entities.dat")));
			$entities->save();
			$tileEntities = new Config($this->path."tileEntities.yml", CONFIG_YAML, unserialize(file_get_contents($this->path."tileEntities.dat")));
			$tileEntities->save();
		}elseif(file_exists($this->path."chunks.dat") and file_exists($this->path."level.dat")){ //Pocket
			$nbt = new NBT();
			$level = parseNBTData($nbt->loadFile($this->path."level.dat"));
			if($level["LevelName"] == ""){
				$level["LevelName"] = "world".time();
			}
			console("[INFO] Importing Pocket level \"".$level["LevelName"]."\" to PMF format");
			unset($level["Player"]);
			$entities = parseNBTData($nbt->loadFile($this->path."entities.dat"));
			if(!isset($entities["TileEntities"])){
				$entities["TileEntities"] = array();
			}
			$tileEntities = $entities["TileEntities"];
			$entities = $entities["Entities"];
			$entities = new Config($this->path."entities.yml", CONFIG_YAML, $entities);
			$entities->save();
			$tileEntities = new Config($this->path."tileEntities.yml", CONFIG_YAML, $tileEntities);
			$tileEntities->save();
		}else{
			return false;
		}
		
		$pmf = new PMFLevel($this->path."level.pmf", array(
			"name" => $level["LevelName"],
			"seed" => $level["RandomSeed"],
			"time" => $level["Time"],
			"spawnX" => $level["SpawnX"],
			"spawnY" => $level["SpawnY"],
			"spawnZ" => $level["SpawnZ"],
			"extra" => "",
			"width" => 16,
			"height" => 8				
		));
		$chunks = new ChunkParser();
		$chunks->loadFile($this->path."chunks.dat");
		$chunks->loadMap();
		for($Z = 0; $Z < 16; ++$Z){
			for($X = 0; $X < 16; ++$X){
				$chunk = array(
					0 => "",
					1 => "",
					2 => "",
					3 => "",
					4 => "",
					5 => "",
					6 => "",
					7 => ""					
				);
				for($z = 0; $z < 16; ++$z){
					for($x = 0; $x < 16; ++$x){
						$block = $chunks->getChunkColumn($X, $Z, $x, $z, 0);
						$meta = $chunks->getChunkColumn($X, $Z, $x, $z, 1);
						for($Y = 0; $Y < 8; ++$Y){
							$chunk[$Y] .= substr($block, $Y << 4, 16);
							$chunk[$Y] .= substr($meta, $Y << 3, 8);
							$chunk[$Y] .= "\x00\x00\x00\x00\x00\x00\x00\x00";
						}
					}
				}
				foreach($chunk as $Y => $data){
					$pmf->setMiniChunk($X, $Z, $Y, $data);
				}
				$pmf->saveChunk($X, $Z);
			}
			console("[NOTICE] Importing level ".ceil(($Z + 1)/0.16)."%");
		}
		$chunks->map = null;
		$chunks = null;
		@unlink($this->path."level.dat");
		@unlink($this->path."level.dat_old");
		@unlink($this->path."player.dat");
		@unlink($this->path."entities.dat");
		@unlink($this->path."chunks.dat");
		@unlink($this->path."chunks.dat.gz");
		@unlink($this->path."tileEntities.dat");
		unset($chunks, $level, $entities, $tileEntities, $nbt);
		return true;
	}

}