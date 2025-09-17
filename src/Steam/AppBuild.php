<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Steam;

use Mintopia\VDFKeyValue\Encoder;

class AppBuild {
    
    private $data = [
        "Depots" => []
    ];
    
    /**
     *
     * @param string $appId
     *            your AppID
     * @param string $description
     *            internal description for this build
     * @param string $contentPath
     *            root content folder, relative to location of this file
     * @param string $buildPath
     *            build output folder for build logs and build cache files
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
     *
     * @param string $depotId
     *            depot ID
     * @param string $localPath
     *            all files from contentroot folder
     * @param string $depotPath
     *            mapped into the root of the depot
     * @param string $recursive
     *            include all subfolders
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
     *
     * @param string $branch
     *            name of the branch to set live
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
    
    private function fixEncoding(string &$value) {
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