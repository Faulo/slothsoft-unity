Farah Module: Unity CLI
=======================
[![Packagist Version](https://img.shields.io/packagist/v/slothsoft/unity)](https://packagist.org/packages/slothsoft/unity)
[![PHP Version Support](https://img.shields.io/packagist/php-v/slothsoft/unity)](https://www.php.net/)
[![Documentation](https://img.shields.io/badge/docs-reference-blue.svg)](https://faulo.github.io/slothsoft-unity/)
[![Test Status](https://github.com/Faulo/slothsoft-unity/actions/workflows/ci-tests.yml/badge.svg)](https://github.com/Faulo/slothsoft-unity/actions/workflows/ci-tests.yml)
[![license badge](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

An API for common Unity problems.

## CLI interface

All commands are executed via Composer's [exec](https://getcomposer.org/doc/03-cli.md#exec).

### autoversion

```
> composer exec autoversion

Determine the version of a repository based on its tags and commits.
    
Usage:
composer exec autoversion plastic|git "path/to/repository"
```

### steam-buildfile

```
> composer exec steam-buildfile

Create a steam build file.
    
Usage:
composer exec steam-buildfile "path/to/root" "path/to/logs" AppID [DepotID=path/to/depot]+ [SetLive]
```

### unity-build

```
> composer exec unity-build

Run all tests inside a Unity project.
    
Usage:
composer exec unity-build "path/to/project" ["path/to/build"] [Platform]
```

### unity-documentation

```
Create a documentation template for DocFX. The template will be created in a ".Documentation" folder in the project root and can be called from there with "dotnet tool run docfx". The generated documentation will be placed in ".Documentation/html".
    
Usage:
composer exec unity-documentation "path/to/project" [template=default+mermaid]

Supported templates:
- default
- default+mermaid
- unity
- singulinkfx+mermaid
```

### unity-help

```
Shortcut for calling "unityhub help". Can be used to verify that your Unity Hub installation was succesully located.
    
Usage:
composer exec unity-help
```

### unity-method

```
Run a specific method inside a Unity project.
    
Usage:
composer exec unity-method "path/to/project" "Method.To.Execute" ["additional params", ...]
```

### unity-module-install

```
Install modules for use in a Unity project. Check for available modules by using the "unity-help" command.
    
Usage:
composer exec unity-module-install "path/to/project" [module-id]+
```

### unity-package-install

```
Create a new Unity project and install a local package into it.
    
Usage:
composer exec unity-package-install "path/to/project/Packages/path-to-package" "path/to/new-project"
```

### unity-project-setting

```
Get one of the project settings of a Unity project.
    
Usage:
composer exec unity-project-setting "path/to/project" "setting-name"
```

### unity-project-version

```
Get or set the projectVersion of a Unity project.
    
Usage:
composer exec unity-project-version "path/to/project" get|set ["new-version"]
```

### unity-tests

```
Run all tests inside a Unity project.
    
Usage:
composer exec unity-tests "path/to/project" [EditMode|PlayMode|Platform]+
```

## Unity Accelerator

In order to use a [Unity Accelerator](https://docs.unity3d.com/Manual/UnityAccelerator.html), set the environment variable "UNITY_ACCELERATOR_ENDPOINT" to an "ip:port".