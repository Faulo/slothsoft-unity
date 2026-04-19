def runTests(def versions) {
	for (version in versions) {
		stage("PHP: ${version}") {
			dir('.reports') {
				deleteDir()
			}

			docker.image("faulo/farah:${version}").inside {
				catchError(stageResult: 'UNSTABLE', buildResult: 'UNSTABLE', catchInterruptions: false) {
					if (env.FARAH_INSTALL_FIREFOX == '1') {
						if (isUnix()) {
							// already part of the farah image
						} else {
							callShell "choco install Firefox --no-progress --yes --skip-checksums --params='/NoTaskbarShortcut /NoDesktopShortcut /NoStartMenuShortcut /NoAutoUpdate'"
						}
					}
				}

				catchError(stageResult: 'UNSTABLE', buildResult: 'UNSTABLE', catchInterruptions: false) {
					callShell 'composer update --prefer-lowest'
					callShell "composer exec phpunit -- --log-junit .reports/${version}-lowest.xml"
				}

				catchError(stageResult: 'UNSTABLE', buildResult: 'UNSTABLE', catchInterruptions: false) {
					callShell 'composer update --prefer-stable'
					callShell "composer exec phpunit -- --log-junit .reports/${version}-stable.xml"
				}
			}

			dir('.reports') {
				junit "*"
			}
		}
	}
}

pipeline {
	agent none
	options {
		disableConcurrentBuilds()
		disableResume()
	}
	environment {
		COMPOSER_PROCESS_TIMEOUT = '3600'
		FARAH_INSTALL_FIREFOX = '0'
	}
	stages {
		stage('Linux') {
			agent {
				label 'docker && linux'
			}
			steps {
				script {
					runTests(["8.2", "8.3", "8.4", "8.5"])
				}
			}
		}
		stage('Windows') {
			agent {
				label 'docker && windows'
			}
			steps {
				script {
					runTests(["8.2", "8.3", "8.4", "8.5"])
				}
			}
		}
	}
}