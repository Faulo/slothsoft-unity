def runTests() {
	callShell 'composer update --prefer-lowest'

	dir('.reports') {
		deleteDir()
	}

	def report = ".reports/${version}.xml"

	catchError(stageResult: 'UNSTABLE', buildResult: 'UNSTABLE', catchInterruptions: false) {
		callShell "composer exec phpunit -- --log-junit ${report}"
	}

	if (fileExists(report)) {
		junit report
	}
}

def runTestsInContainer(def image, def versions) {
	for (version in versions) {
		def name = "${image}:${version}"

		stage("PHP: ${version}") {
			callShell "docker pull ${name}"

			docker.image(name).inside {
				runTests();
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
	}
	stages {
		stage('Unity') {
			agent {
				label 'unity && linux'
			}
			steps {
				script {
					runTests()
				}
			}
		}
		stage('Linux') {
			agent {
				label 'docker && linux'
			}
			steps {
				script {
					runTestsInContainer("faulo/farah", [
						"7.4",
						"8.0",
						"8.1",
						"8.2",
						"8.3"
					])
				}
			}
		}
		stage('Windows') {
			agent {
				label 'docker && windows'
			}
			steps {
				script {
					runTestsInContainer("faulo/farah", [
						"7.4",
						"8.0",
						"8.1",
						"8.2",
						"8.3"
					])
				}
			}
		}
	}
}