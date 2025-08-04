<?php
namespace karasu;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\Position;
use pocketmine\world\World;

class Main extends PluginBase implements Listener {

    private array $customSpawns = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->loadCustomSpawns();
    }

    private function loadCustomSpawns(): void {
        $data = $this->getConfig()->get("custom-spawns", []);
        if(is_array($data)) {
            $this->customSpawns = $data;
        }
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        
        if($player->hasPermission($this->getConfig()->get("bypass-permission"))) {
            return;
        }

        $worldName = $this->getConfig()->get("custom-world", "");
        $worldManager = $this->getServer()->getWorldManager();
        $world = $worldName !== "" ? $worldManager->getWorldByName($worldName) : $worldManager->getDefaultWorld();

        if(!$world instanceof World) {
            $this->getLogger()->error("Target world not found!");
            return;
        }

        $spawn = $this->getSpawnLocation($world);
        
        $this->getScheduler()->scheduleDelayedTask(
            new ClosureTask(function() use ($player, $spawn): void {
                if($player->isOnline()) {
                    $player->teleport($spawn);
                    $this->sendWelcomeMessage($player);
                }
            }),
            $this->getConfig()->get("teleport-delay", 20)
        );
    }

    private function getSpawnLocation(World $world): Position {
        $worldName = $world->getFolderName();
        return isset($this->customSpawns[$worldName]) 
            ? Position::fromObject($this->customSpawns[$worldName], $world)
            : $world->getSpawnLocation();
    }

    private function sendWelcomeMessage(Player $player): void {
        $config = $this->getConfig()->get("welcome-title", []);
        if($config["enabled"] ?? true) {
            $player->sendTitle(
                $config["title"] ?? "§aWelcome!",
                $config["subtitle"] ?? "§7Teleported to spawn",
                20, 60, 20
            );
        }
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool {
        if(!$sender instanceof Player) {
            $sender->sendMessage("This command must be run in-game");
            return true;
        }

        if(strtolower($cmd->getName()) === "setspawn") {
            $world = $sender->getWorld();
            $this->customSpawns[$world->getFolderName()] = [
                "x" => $sender->getPosition()->getX(),
                "y" => $sender->getPosition()->getY(),
                "z" => $sender->getPosition()->getZ()
            ];
            
            $this->getConfig()->set("custom-spawns", $this->customSpawns);
            $this->saveConfig();
            
            $sender->sendMessage("§aSpawn point set for this world!");
            return true;
        }
        
        return false;
    }
}