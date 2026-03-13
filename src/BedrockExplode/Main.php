<?php

namespace BedrockExplode;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener{

    private Config $config;

    private array $activeTNT = [];
    private array $bedrockDamage = [];

    public function onEnable() : void{
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onPlace(BlockPlaceEvent $event) : void{

        $player = $event->getPlayer();

        foreach($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]){

            if($block->getTypeId() === VanillaBlocks::TNT()->getTypeId()){

                if(isset($this->activeTNT[$player->getName()])){
                    $event->cancel();
                    $player->sendMessage($this->config->getNested("messages.already-active"));
                    return;
                }

                $this->activeTNT[$player->getName()] = true;
                $player->sendMessage($this->config->getNested("messages.place-tnt"));
            }
        }
    }

    public function onExplode(EntityExplodeEvent $event) : void{

        $center = $event->getPosition();
        $world = $center->getWorld();
        $radius = 3;

        $required = $this->config->get("tnt-required");

        foreach($this->activeTNT as $playerName => $v){

            $player = $this->getServer()->getPlayerExact($playerName);

            if($player === null){
                unset($this->activeTNT[$playerName]);
                continue;
            }

            $bedrockFound = false;
            $remainingMsg = null;
            $destroyed = false;

            for($x = -$radius; $x <= $radius; $x++){
                for($y = -$radius; $y <= $radius; $y++){
                    for($z = -$radius; $z <= $radius; $z++){

                        $block = $world->getBlockAt(
                            $center->getFloorX() + $x,
                            $center->getFloorY() + $y,
                            $center->getFloorZ() + $z
                        );

                        if($block->getTypeId() === VanillaBlocks::BEDROCK()->getTypeId()){

                            $bedrockFound = true;

                            $pos = $block->getPosition();
                            $key = $pos->getX().":".$pos->getY().":".$pos->getZ();

                            if(!isset($this->bedrockDamage[$key])){
                                $this->bedrockDamage[$key] = 0;
                            }

                            $this->bedrockDamage[$key]++;

                            if($this->bedrockDamage[$key] >= $required){

                                $world->setBlock($pos, VanillaBlocks::AIR());
                                unset($this->bedrockDamage[$key]);
                                $destroyed = true;

                            }else{

                                $remaining = $required - $this->bedrockDamage[$key];
                                $remainingMsg = $remaining;
                            }
                        }
                    }
                }
            }

            if(!$bedrockFound){

                $player->sendMessage($this->config->getNested("messages.not-touching"));

            }elseif($destroyed){

                $player->sendMessage($this->config->getNested("messages.success"));

            }elseif($remainingMsg !== null){

                $msg = str_replace(
                    "{remaining}",
                    $remainingMsg,
                    $this->config->getNested("messages.progress")
                );

                $player->sendMessage($msg);
            }

            unset($this->activeTNT[$playerName]);
        }
    }
}
