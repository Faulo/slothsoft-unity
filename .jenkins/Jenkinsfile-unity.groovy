def runTests(def name = "tests") {
    catchError(stageResult: 'UNSTABLE', buildResult: 'UNSTABLE', catchInterruptions: false) {
        exec "composer update --prefer-lowest --no-interaction --no-progress"

        dir('.reports') {
            deleteDir()
        }

        def report = ".reports/${name}.xml"

        exec "composer exec -- phpunit --log-junit ${report}"

        if (fileExists(report)) {
            junit report
        }
    }
}

pipeline {
    agent none
    options {
        disableConcurrentBuilds()
        disableResume()
        disableRestartFromStage()
    }
    environment {
        COMPOSER = 'composer.json'
        JENKINS_UNITY_ENV = "COMPOSER"
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
