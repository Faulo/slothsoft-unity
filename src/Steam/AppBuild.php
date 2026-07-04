<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Steam;

use Mintopia\VDFKeyValue\Encoder;

/**
 * Builds Steam app build VDF files for SteamCMD uploads.
 *
 * @author Daniel Schulz
 * @since 2022-08-05
 */
final class AppBuild {
    
    private array $data = [
        "Depots" => []
    ];
    
    /**
     * Creates an app build with the Steam app ID, description, content root, and build output path.
     */
    public function __construct(string $appId, string $description, string $contentPath, string $buildPath) {
        $this->fixEncoding($appId);
        $this->fixEncoding($description);
        $this->fixEncoding($contentPath);
        $this->fixEncoding($buildPath);
        
        $this->data['AppID'] = $appId;
        $this->data['Desc'] = $description;
        $this->data['ContentRoot'] = $contentPath;
        $this->data['BuildOutput'] = $buildPath;
    }
    
    /**
     * Adds a depot file mapping to this app build.
     */
    public function addDepot(string $depotId, string $localPath = '*', string $depotPath = '.', string $recursive = '1'): void {
        $this->fixEncoding($depotId);
        $this->fixEncoding($localPath);
        $this->fixEncoding($depotPath);
        $this->fixEncoding($recursive);
        
        $this->data['Depots'][$depotId] = [
            "FileMapping" => [
                "LocalPath" => $localPath,
                "DepotPath" => $depotPath,
                "recursive" => $recursive
            ]
        ];
    }
    
    /**
     * Marks the uploaded build live on the given Steam branch.
     */
    public function setLive(string $branch): void {
        $this->fixEncoding($branch);
        
        $this->data['SetLive'] = $branch;
    }
    
    const SUPPORTED_ENCODINGS = [
        'UTF-8',
        'Windows-1252',
        'ISO-8859-1'
    ];
    
    private function fixEncoding(string &$value): void {
        $encoding = mb_detect_encoding($value, self::SUPPORTED_ENCODINGS);
        if ($encoding and $encoding !== 'UTF-8') {
            $value = mb_convert_encoding($value, 'UTF-8', $encoding);
        }
    }
    
    public function __toString(): string {
        $encoder = new Encoder();
        return $encoder->encode('AppBuild', $this->data);
    }
}
