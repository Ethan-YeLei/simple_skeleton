<?php
/**
 * 安装程序主流程
 * @author Ethan.Ye
 * @date 2020/9/02 10:43 上午
 */

declare(strict_types=1);

namespace Installer;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\Link;
use Composer\Package\Version\VersionParser;

/**
 * Class InstallMain 脚手架核心类
 * @package Installer
 */
class InstallMain
{

    // io 用于在控制台输出信息
    protected $io;

    // composer对象
    protected $composer;

    // composer.json 地址
    protected $composerFile;

    // 当前项目根目录
    protected $projectPath;

    // 项目安装目录
    protected $installPath;

    // 配置文件地址
    protected $config;

    // composer.json文件解析成数组
    protected $composerDefinition = [];

    // json解析器, 解析器里面存储了composer.json的内容
    protected $composerJson;

    // 其实就是 composer.json为配置生成的composer对象包
    protected $composerPackage;

    // composer对象包中的require的第三方组件信息
    protected $composerRequires;

    // composer对象包中的require-dev的第三方组件信息
    protected $composeDevRequires;

    protected $stabilityFlags;

    // 程序运行临时目录
    protected $runtimeDir;

    public function __construct(IOInterface $IO, Composer $composer)
    {
        // 获取IO流，用于写数据输出到控制台
        $this->io = $IO;

        // 存储composer对象
        $this->composer = $composer;

        // composer文件地址  composer.json
        $this->composerFile = realpath(Factory::getComposerFile());

        // 项目地址
        $this->projectPath = rtrim(realpath(dirname($this->composerFile)), "/\\") . '/';

        // 安装路径
        $this->installPath = __DIR__ . "/";

        // 引入配置文件
        $this->config = require_once __DIR__ . "/config.php";

        // 初始化 解析composer.json文件和composer相关信息
        $this->_InitComposer($this->composer, $this->composerFile);

        // 初始化临时运行 目录
        $this->_InitRuntimeDir();
    }

    /*
     * $composer Composer对象
     * $composerFile composer.json 文件地址
     */
    private function _InitComposer(Composer $composer, string $composerFile)
    {
        // 将composer.json的内容存储到数组composerDefinition中
        $this->composerJson = new JsonFile($composerFile);
        $this->composerDefinition = $this->composerJson->read();

        // 获取composer对象中composer.json中相关信息
        $this->composerPackage = $composer->getPackage();
        $this->composerRequires = $this->composerPackage->getRequires();
        $this->composeDevRequires = $this->composerPackage->getDevRequires();
    }

    // 初始化生成临时运行目录
    private function _InitRuntimeDir()
    {
        // 打印信息到控制台
        $this->io->write("<info> Setup data and cache dir, Please Waiting...</info>", true, IOInterface::NORMAL);
        $runtimeDir = $this->projectPath . "/runtime";
        if (!is_dir($runtimeDir)) {
            mkdir($runtimeDir, 0775, true);
            chmod($runtimeDir, 0755);
        }
        $this->runtimeDir = $runtimeDir;
    }

    /*
     * 安装可选包
     */
    protected function InstallOptionalPackages(): void
    {
        // 1. 获取config文件中 key=optional, 的内容(可选包)
        $options = Util::GetArrayItem($this->config, 'optional', []);
        if (empty($options)) return;

        // 2.遍历可选包
        foreach ($options as $packageName => $config) {
            // 可选组件是否安装询问
            $isInstall = $this->_AskInstallOptionalPackage($packageName, $config);
            if ($isInstall === false) {
                $this->io->write(sprintf('  <comment>You will skip install %s component</comment>', $packageName));
                continue;
            }

            // 开始安装组件
            $this->_AddPackage($packageName, $config);
        }
    }

    // 开始安装包, 修改composer.json解析出来的composerDefinition 和 composer.json生成的对象 composerRequires
    private function _AddPackage(string $packageName, array $config) {
        $version = (string)Util::GetArrayItem($config, "version", "^1.0");
        $this->io->write(sprintf('  - Adding package <info>%s</info> (<comment>%s</comment>)', $packageName, $version));
        $versionParser = new VersionParser();
        $constraint =  $versionParser->parseConstraints($version);
        $link = new Link('__root__', $packageName, $constraint, 'requires', $version);
        $this->composerRequires[$packageName] = $link;
        $this->composerDefinition['require'][$packageName] = $version;
    }

    /**
     * @param string $packageName 可选组件包名
     * @param array $config 可选组件包的配置信息 比如版本号等
     * @return bool  是否出错
     */
    private function _AskInstallOptionalPackage(string $packageName, array $config): bool
    {
        $version = (string)Util::GetArrayItem($config, 'version');

        while (true) {
            $answer = $this->io->ask(sprintf("Install <info>%s</info> (<comment>%s</comment>) package ? Enter your choice <comment>[Y/N]</comment>: ", $packageName, $version));
            if (!$answer) $answer = 'Y';
            if (in_array(strtolower($answer),['y', 'yes'])) {
                return true;
            } elseif(in_array(strtolower($answer),['n', 'no'])) {
                return false;
            }
        }

        return false;
    }

    /**
     * 同步最新的信息到composerPackage包对象中
     */
    protected function SyncCompserPackage() {
        $this->composerPackage->setRequires($this->composerRequires);
        $this->composerPackage->setDevRequires($this->composeDevRequires);
        $this->composerPackage->setAutoload($this->composerDefinition['autoload']);
        $this->composerPackage->setDevAutoload($this->composerDefinition['autoload-dev']);
    }

    /**
     * 清理带清理的composer.json的配置
     */
    protected function CleanUp() {
        $this->io->write('<info>Remove installer script</info>');
        // 1、移除composer内的安装脚本配置
        unset(
            $this->composerDefinition['autoload']['psr-4']['Installer\\'],
            $this->composerDefinition['scripts']['pre-update-cmd'],
            $this->composerDefinition['scripts']['pre-install-cmd'],
            $this->composerDefinition['scripts']['post-install-cmd'],
            $this->composerDefinition['scripts']['post-update-cmd'],
            $this->composerDefinition['scripts']['post-root-package-install']
        );

        // 2.删除install目录
        Util::RecursiveRmdir($this->installPath);

        // 3.固化composer.json
        $this->composerJson->write($this->composerDefinition);
    }

    public function run(): void
    {
        $this->io->write('<info> Init Project, Please Waiting ... </info>', true, IOInterface::NORMAL);

        // 安装可选包
        $this->InstallOptionalPackages();

        $this->SyncCompserPackage();

        $this->CleanUp();

    }
}