def runTests(def name = "tests") {
    fileOperations([folderDeleteOperation('.reports'), folderCreateOperation('.reports')])

    catchError(stageResult: 'UNSTABLE', buildResult: 'UNSTABLE', catchInterruptions: false) {
        exec "composer update --no-interaction --no-progress --prefer-lowest"

        exec "php vendor/bin/phpunit --log-junit .reports/${name}.xml"
    }

    junit(testResults: '.reports/*.xml', allowEmptyResults: true)
}

pipeline {
    agent none
    options {
        disableConcurrentBuilds()
        disableResume()
        disableRestartFromStage()
    }
    environment {
        COMPOSER = ''
        COMPOSER_VENDOR_DIR = ''
        JENKINS_UNITY_ENV = "COMPOSER:COMPOSER_VENDOR_DIR"
        COMPOSER_PROCESS_TIMEOUT = '3600'
        EMAIL_TEST_TIME = '1745158411'
        EMAIL_TEST_CODE = '177824'
        UNITY_LOGGING = ''
    }
    stages {
        stage('Linux') {
            agent {
                label 'unity && linux'
            }
            steps {
                script {
                    withUnity {
                        runTests()
                    }
                }
            }
        }
        stage('Windows') {
            agent {
                label 'unity && windows'
            }
            steps {
                script {
                    withUnity {
                        runTests()
                    }
                }
            }
        }
    }
}
