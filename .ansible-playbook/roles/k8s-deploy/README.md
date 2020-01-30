Playbook to Deploy Jenkins CI/CD
=========

Deploy Jenkins playbook.

Requirements
------------

pip install --user openshift

Role Variables
--------------

source\_dir - Git source directory for this project. Deployment files will be loaded from source\_dir/cicd/pipeline.
k8s\_namespace - Namespace to deploy to. Defaults to `tatproxy-cicd`.

Dependencies
------------

A list of other roles hosted on Galaxy should go here, plus any details in regards to parameters that may need to be set for other roles, or variables that are used from other roles.

Example Playbook
----------------

`ansible-playbook -v -e "source_dir=~/hackathon/tatproxy/cicd/pipeline" jenkins.yml`

License
-------

BSD

Author Information
------------------

An optional section for the role authors to include contact information, or a website (HTML is not allowed).
