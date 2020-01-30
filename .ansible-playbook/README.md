Playbook to Deploy Kubernetes Objects
=========

Deploy Jenkins playbook.

Requirements
------------

pip install --user openshift

Role Variables
--------------

source\_dir - Absolute path of the directory to deploy. Should contain Kubernetes or Openshift objects ready for use with `oc apply`.  
k8s\_namespace - Namespace to deploy to. Defaults to `tatproxy-cicd`.

Example Playbook
----------------

Deploy Jenkins instance and pipeline:  
`ansible-playbook -v -e "source_dir=~/hackathon/tatproxy/cicd/pipeline" k8s-deploy.yml`

License
-------

BSD

Author Information
------------------

Jonah Howard <jonah@omnitracs.com>
Rhiannon Savage <rhsavage@omnitracs.com>
tbox <tbox@redhat.com>
