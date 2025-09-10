def runTests(def name = "tests") {
	callShell 'composer update --prefer-lowest'

	dir('.reports') {
		deleteDir()
	}

	def report = ".reports/${name}.xml"

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
				runTests(version);
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
		stage('Linux Unity') {
			agent {
				label 'unity && linux'
			}
			steps {
				script {
					runTests()
				}
			}
		}
		stage('Windows Unity') {
			agent {
				label 'unity && windows'
			}
			steps {
				script {
					runTests()
				}
			}
		}
		stage('Linux Farah') {
			agent {
				label 'docker && linux'
			}
			steps {
				script {
					runTestsInContainer("faulo/farah", [
						"8.2"
					])
				}
			}
		}
		stage('Windows Farah') {
			agent {
				label 'docker && windows'
			}
			steps {
				script {
					runTestsInContainer("faulo/farah", [
						"8.2"
					])
				}
			}
		}
	}
}