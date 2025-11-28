# 📋 Setup Checklist - Projeto CI/CD com Kubernetes

Este documento guia a configuração completa do projeto para deploy em GCP Kubernetes com Bastion.

---

## 1. ✅ Pré-requisitos

### 1.1 GCP / Kubernetes
- [ ] Cluster GKE criado em GCP
- [ ] Zona e nome do cluster conhecidos (`GKE_CLUSTER_NAME`, `GKE_ZONE`)
- [ ] Bastion VM criado (se usar acesso via SSH)
- [ ] Service Account GCP com permissões para Kubernetes

### 1.2 Docker Hub
- [ ] Conta Docker Hub criada
- [ ] Repositório(s) criados (ex: `seu-usuario/frontend`, `seu-usuario/backend`, `seu-usuario/mysql-db`)

### 1.3 GitLab
- [ ] Repositório Git criado
- [ ] Acesso ao CI/CD Variables configurado

---

## 2. ⚙️ Configurar CI/CD Variables no GitLab

Acesse **Settings → CI/CD → Variables** e adicione:

### 2.1 Autenticação Docker Hub
```
DOCKERHUB_USERNAME          = seu-usuario-docker
DOCKERHUB_TOKEN            = seu-token-docker (Personal Access Token)
DOCKERHUB_REPO_PREFIX      = seu-usuario-docker
```

### 2.2 Autenticação GCP
```
GOOGLE_SERVICE_ACCOUNT_KEY = <base64 da service account JSON>
                            # Gerar: cat service-account.json | base64 -w 0
GCP_PROJECT_ID            = seu-projeto-gcp
GKE_CLUSTER_NAME          = seu-cluster-nome
GKE_ZONE                  = us-central1-a  # (ajustar conforme sua zona)
```

### 2.3 Credenciais Banco de Dados (Protegidas!)
```
DB_USER                   = meubanco_user       (Masked & Protected)
DB_PASSWORD               = senha-segura-123    (Masked & Protected)
DB_ROOT_PASSWORD          = senha-root-456      (Masked & Protected)
```

### 2.4 Configuração Bastion (opcional, se usar SSH)
```
BASTION_USER              = seu-usuario-ssh
BASTION_HOST              = 35.123.456.789      # IP público da Bastion
BASTION_KEY               = <base64 da chave SSH privada>
                          # Gerar: cat ~/.ssh/id_rsa | base64 -w 0
KUBERNETES_API            = kubernetes-api-interno.meu-cluster.svc.cluster.local
```

> **Dica**: Marque variáveis sensíveis como **Protected** e **Masked** para não aparecerem em logs.

---

## 3. 🔑 Gerar e Configurar Credenciais GCP

### 3.1 Service Account
```bash
gcloud iam service-accounts create gitlab-deploy --display-name="GitLab Deploy"

gcloud projects add-iam-policy-binding PROJECT_ID \
  --member=serviceAccount:gitlab-deploy@PROJECT_ID.iam.gserviceaccount.com \
  --role=roles/container.developer

gcloud iam service-accounts keys create /tmp/gitlab-sa-key.json \
  --iam-account=gitlab-deploy@PROJECT_ID.iam.gserviceaccount.com

# Encodar para CI Variable
cat /tmp/gitlab-sa-key.json | base64 -w 0 > /tmp/sa-base64.txt
# Copiar conteúdo para GOOGLE_SERVICE_ACCOUNT_KEY
```

### 3.2 Chave SSH para Bastion (se usar)
```bash
ssh-keygen -t rsa -b 4096 -f ~/.ssh/bastion_key -N ""

# Copiar chave pública para autorizado_keys da Bastion:
cat ~/.ssh/bastion_key.pub | ssh your-bastion-user@BASTION_IP "cat >> ~/.ssh/authorized_keys"

# Encodar para CI Variable
cat ~/.ssh/bastion_key | base64 -w 0
```

---

## 4. 📦 Estrutura de Arquivos Esperada

```
projeto-root/
├── .gitlab-ci.yml              ✅ Pipeline CI/CD
├── Dockerfile.frontend         ✅ Frontend Apache
├── Dockerfile.backend          ✅ Backend PHP-Apache
├── README.md                   ✅ Documentação
├── SETUP_CHECKLIST.md          ✅ Este arquivo
│
├── frontend/
│   └── index.html              ✅ App frontend
│
├── backend/
│   ├── index.php               ✅ Receber mensagens
│   └── get_messages.php        ✅ Listar mensagens
│
├── database/
│   ├── Dockerfile.db           ✅ MySQL Dockerfile
│   └── schema.sql              ✅ Schema e dados iniciais
│
└── kubernetes/
    ├── deployment.yml          ✅ 3 Deployments separados
    ├── service.yml             ✅ 3 Services (frontend, backend, mysql)
    ├── persistence.yml         ✅ PVC para MySQL
    └── secrets.yxxml           ℹ️ Template (gerado via CI)
```

---

## 5. 🚀 Deploy Local (Teste antes de GCP)

### 5.1 Com Docker Compose

Crie `docker-compose.yml` na raiz:

```yaml
version: '3.9'

services:
  frontend:
    build:
      context: .
      dockerfile: Dockerfile.frontend
    ports:
      - "8000:80"
    depends_on:
      - backend

  backend:
    build:
      context: .
      dockerfile: Dockerfile.backend
    ports:
      - "8080:8080"
    environment:
      DB_HOST: mysql
      DB_USER: meubanco_user
      DB_PASSWORD: senha123
      DB_NAME: meubanco
    depends_on:
      - mysql

  mysql:
    build:
      context: database
      dockerfile: Dockerfile.db
    ports:
      - "3306:3306"
    environment:
      MYSQL_DATABASE: meubanco
      MYSQL_USER: meubanco_user
      MYSQL_PASSWORD: senha123
      MYSQL_ROOT_PASSWORD: root123
    volumes:
      - mysql_data:/var/lib/mysql

volumes:
  mysql_data:
```

**Executar**:
```bash
docker-compose up -d
# Frontend: http://localhost:8000
# Backend: http://localhost:8080/index.php
```

---

## 6. ☁️ Deploy em GCP Kubernetes

### 6.1 Verificar cluster

```bash
# Obter credentials
gcloud container clusters get-credentials GKE_CLUSTER_NAME --zone GKE_ZONE

# Verificar conexão
kubectl cluster-info
kubectl get nodes
```

### 6.2 Aplicar manifests manualmente (teste)

```bash
# Criar namespace (opcional)
kubectl create namespace meubanco

# Criar Secret
kubectl create secret generic db-secrets \
  --from-literal=DB_USER=meubanco_user \
  --from-literal=DB_PASSWORD=senha-segura \
  --from-literal=DB_ROOT_PASSWORD=senha-root \
  --from-literal=DB_HOST=mysql-service

# Aplicar manifests
kubectl apply -f kubernetes/persistence.yml
kubectl apply -f kubernetes/deployment.yml
kubectl apply -f kubernetes/service.yml

# Verificar status
kubectl get deployments
kubectl get services
kubectl get pods
```

### 6.3 Via Bastion SSH

```bash
# No seu computador local:
ssh -i ~/.ssh/bastion_key -N -L 6443:kubernetes-api-internal:6443 bastion-user@BASTION_IP &

# Configurar kubeconfig para usar túnel
# Editar ~/.kube/config e mudar server para: https://localhost:6443

# Depois:
kubectl get pods
```

---

## 7. 🔍 Testes de Conectividade

### 7.1 Frontend acessa Backend?

```bash
# Dentro de pod frontend:
kubectl exec -it <frontend-pod> -- sh

# No container:
curl http://backend-service:8080/get_messages.php
```

### 7.2 Backend acessa MySQL?

```bash
# Dentro de pod backend:
kubectl exec -it <backend-pod> -- sh

# No container:
mysql -h mysql-service -u meubanco_user -p meubanco -e "SELECT * FROM mensagens;"
```

### 7.3 LoadBalancer funciona?

```bash
# Obter IP externo
kubectl get svc frontend-service

# Acessar no navegador
curl http://<EXTERNAL-IP>:80
```

---

## 8. 📊 Monitoramento e Logs

```bash
# Logs de um pod
kubectl logs <pod-name>
kubectl logs -f <pod-name>  # tail em tempo real

# Logs de um deployment
kubectl logs -l app=frontend --tail=50

# Status dos pods
kubectl get pods -o wide
kubectl describe pod <pod-name>

# Events
kubectl get events --sort-by='.lastTimestamp'
```

---

## 9. 🔐 Boas Práticas de Segurança

- [ ] Variáveis sensíveis marcadas como **Masked** no GitLab
- [ ] Usar **Protected** variables para produção
- [ ] Limpar logs de credenciais no after_script do CI
- [ ] Usar namespaces separados (dev, staging, prod)
- [ ] Network Policies para restringir tráfego
- [ ] RBAC: ServiceAccounts com roles mínimas
- [ ] Imagens base de repositórios oficiais (php:8.2-apache, mysql:8.0, httpd:2.4-alpine)
- [ ] Scanning de imagens com Trivy/Snyk

---

## 10. 📝 Variáveis de Ambiente do Frontend

O frontend agora detecta automaticamente:
- **Localhost/Dev**: Usa `http://localhost:8080`
- **Kubernetes/Prod**: Usa `http://backend-service:8080`

Adaptação no `frontend/index.html`:
```javascript
const backendUrl = window.location.hostname === 'localhost' 
  ? 'http://localhost:8080/get_messages.php'
  : 'http://backend-service:8080/get_messages.php';
```

---

## 11. 🆘 Troubleshooting

| Problema | Solução |
|----------|---------|
| Pod em `CrashLoopBackOff` | `kubectl logs <pod>` e verificar variáveis ENV |
| Frontend não acessa backend | Verificar DNS do Service: `nslookup backend-service` no pod |
| MySQL não inicia | Verificar PVC: `kubectl get pvc` e storage disponível |
| Deploy via CI falha | Verificar variáveis GitLab e Bastion SSH |
| Imagens não fazem push | Verificar credenciais Docker Hub e nome do repo |

---

## 12. 📞 Contato / Referências

- [GCP Kubernetes Documentation](https://cloud.google.com/kubernetes-engine/docs)
- [GitLab CI/CD](https://docs.gitlab.com/ee/ci/)
- [Kubernetes Best Practices](https://kubernetes.io/docs/concepts/configuration/overview/)

---

**Última atualização**: 27 de Novembro de 2025  
**Versão**: 1.0
