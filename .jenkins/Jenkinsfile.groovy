def unstable(def body) {
	catchError(stageResult: 'UNSTABLE', buildResult: 'UNSTABLE', catchInterruptions: false) {
		body()
	}
}

def report(def body) {
    checkout scm

    fileOperations([folderDeleteOperation('.reports'), folderCreateOperation('.reports')])

    body()

    junit(testResults: '.reports/*.xml', allowEmptyResults: true)
}

def runComposerTest(def version, def variant) {
	unstable {
        prepareComposer()

        exec "composer update --prefer-${variant} --no-interaction --no-progress"

        exec "composer exec phpunit -- --log-junit .reports/${version}-${variant}.xml"
	}
}

def runLinter() {
	unstable {
        prepareComposer()

		exec "composer update --no-interaction --no-progress"

        writeFile(
            file: ".reports/phpstan.xml",
            text: execStdout("composer exec --verbose phpstan -- --no-progress --configuration=phpstan.neon.dist --error-format=junit"),
            encoding: 'UTF-8'
        )
	}
}

def prepareComposer() {
    if (isUnix()) {
        // Use a verified PHAR instead of the image's PHP 8.5-incompatible system Composer.
        exec "php -r \"if (!copy('https://getcomposer.org/download/2.11.1/composer.phar', '.reports/composer.phar')) { exit(1); }\""
        exec "php -r \"if (hash_file('sha256', '.reports/composer.phar') !== '6f1b891e1d4927d62740276c540fb490e47689e19e102c49e8da849794882f8c') { exit(1); }\""
        exec "install -m 0755 .reports/composer.phar /usr/local/bin/composer"
        exec "composer --version"
    }
}

pipeline {
	agent none
	options {
		disableConcurrentBuilds()
		disableResume()
		disableRestartFromStage()
		skipDefaultCheckout()
	}
	environment {
		COMPOSER_PROCESS_TIMEOUT = '3600'
	}
	stages {
		stage('Lint') {
			agent {
                label 'docker'
            }
			steps {
                script {
                    report {
                        docker.image("faulo/farah:8.2").inside {
                            runLinter();
                        }
                    }
                }
			}
		}
		stage('Test') {
			steps {
				script {
					def platforms = [ 'linux', 'windows' ]
					def versions = [ "8.2", "8.3", "8.4", "8.5" ]
					def variants = [ 'lowest', 'stable' ]

					def branches = [:]

					for (def p in platforms) {
						def platform = p
						for (def v in versions) {
							def version = v
							for (def a in variants) {
								def variant = a

								def name = "${platform} php-${version} ${variant}"
								def label = "${platform} && docker"
								def workspace = "php-${version}-${variant}"

								branches[name] = {
									stage(name) {
										node(label) {
											ws("${WORKSPACE}/${workspace}") {
                                                report {
                                                    docker.image("faulo/farah:${version}").inside {
                                                        runComposerTest(version, variant)
                                                    }
                                                }
											}
										}
									}
								}
							}
						}
					}

					parallel branches
				}
			}
		}
	}
}
