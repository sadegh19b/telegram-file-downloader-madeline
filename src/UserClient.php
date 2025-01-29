<?php

namespace TelegramFileDownloaderMadeline;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\EventHandler;
use danog\MadelineProto\Logger;

class UserClient extends EventHandler
{
    private FileHandler $fileHandler;
    private const HELP_MESSAGE = "
🔰 Available commands:
/help - Show this help message
/download [message link] - Download file from message link
Example: /download https://t.me/channel/123

💡 You can also:
- Forward any message with media to me
- Reply to any message with media using /download
- Send me a direct message link
";

    public function __construct(?API $API = null)
    {
        parent::__construct($API);
        $this->fileHandler = new FileHandler();
    }

    public function getReportPeers()
    {
        return [];
    }

    public async function onStart()
    {
        $this->logger->logger('User client started!', Logger::NOTICE);
    }

    public async function onUpdateNewMessage(array $update): \Generator
    {
        if (!isset($update['message']['from_id'])) {
            return;
        }

        try {
            $message = $update['message'];
            $text = $message['message'] ?? '';
            $peer = $update;

            // Handle /start and /help commands
            if ($text === '/start' || $text === '/help') {
                return $this->messages->sendMessage([
                    'peer' => $peer,
                    'message' => self::HELP_MESSAGE,
                    'parse_mode' => 'HTML'
                ]);
            }

            // Handle direct message link
            if (preg_match('/https?:\/\/t\.me\/(?:c\/)?([^\/]+)\/(\d+)/i', $text, $matches)) {
                return yield from $this->handleMessageLink($peer, $text);
            }

            // Handle /download command
            if (strpos($text, '/download') === 0) {
                $link = trim(substr($text, 9));
                if ($link) {
                    return yield from $this->handleMessageLink($peer, $link);
                }
                
                // If no link provided but replying to a message with media
                if (isset($message['reply_to_msg_id']) && isset($message['reply_to'])) {
                    $repliedMessage = yield $this->messages->getMessages([
                        'peer' => $peer,
                        'id' => [$message['reply_to_msg_id']]
                    ]);
                    
                    if (isset($repliedMessage['messages'][0]['media'])) {
                        return yield from $this->handleMedia($peer, $repliedMessage['messages'][0]['media']);
                    }
                }
            }

            // Handle forwarded message with media
            if (isset($message['media'])) {
                return yield from $this->handleMedia($peer, $message['media']);
            }

            // If no valid command or media, show help message
            return $this->messages->sendMessage([
                'peer' => $peer,
                'message' => "I don't understand that command.\n" . self::HELP_MESSAGE,
                'parse_mode' => 'HTML'
            ]);

        } catch (\Throwable $e) {
            return $this->messages->sendMessage([
                'peer' => $update,
                'message' => "❌ Error: " . $e->getMessage(),
                'parse_mode' => 'HTML'
            ]);
        }
    }

    private function handleMedia($peer, $media): \Generator
    {
        yield $this->messages->sendMessage([
            'peer' => $peer,
            'message' => "⏳ Downloading file...",
            'parse_mode' => 'HTML'
        ]);

        try {
            $fileInfo = yield $this->downloadMedia($media);
            
            if (!$fileInfo) {
                throw new \Exception("Failed to download the file");
            }

            $result = $this->fileHandler->saveFile(
                $fileInfo['content'],
                $fileInfo['filename'] ?? 'downloaded_file',
                $fileInfo['mime_type'] ?? 'application/octet-stream'
            );

            return $this->messages->sendMessage([
                'peer' => $peer,
                'message' => $this->formatResponse($result),
                'parse_mode' => 'HTML'
            ]);

        } catch (\Exception $e) {
            return $this->messages->sendMessage([
                'peer' => $peer,
                'message' => "❌ Error downloading file: " . $e->getMessage(),
                'parse_mode' => 'HTML'
            ]);
        }
    }

    private function handleMessageLink($peer, $link): \Generator
    {
        if (!preg_match('/t\.me\/(?:c\/)?([^\/]+)\/(\d+)/', $link, $matches)) {
            return $this->messages->sendMessage([
                'peer' => $peer,
                'message' => "❌ Invalid message link format. Use format: https://t.me/channel/123",
                'parse_mode' => 'HTML'
            ]);
        }

        yield $this->messages->sendMessage([
            'peer' => $peer,
            'message' => "⏳ Fetching message and downloading file...",
            'parse_mode' => 'HTML'
        ]);

        try {
            $channel = $matches[1];
            $messageId = (int)$matches[2];

            $message = yield $this->channels->getMessages([
                'channel' => $channel,
                'id' => [$messageId]
            ]);

            if (empty($message) || !isset($message['messages'][0]['media'])) {
                throw new \Exception("No file found in the message");
            }

            return yield from $this->handleMedia($peer, $message['messages'][0]['media']);

        } catch (\Exception $e) {
            return $this->messages->sendMessage([
                'peer' => $peer,
                'message' => "❌ Error: " . $e->getMessage(),
                'parse_mode' => 'HTML'
            ]);
        }
    }

    private function formatResponse(array $fileInfo): string
    {
        $size = $this->formatFileSize($fileInfo['size']);
        
        return "✅ File downloaded successfully!\n\n" .
               "📁 Filename: {$fileInfo['filename']}\n" .
               "📦 Size: {$size}\n" .
               "🔗 Download URL: {$fileInfo['url']}";
    }

    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }
} 