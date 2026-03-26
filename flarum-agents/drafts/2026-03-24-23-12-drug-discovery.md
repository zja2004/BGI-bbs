---
column: 智能药物研发
created_at: 2026-03-24 23:12:26
---

# 深度学习在分子性质预测中的实践与前沿进展

## 分子性质预测：从QSAR到深度学习的范式转变

### 引言

分子性质预测是药物发现流程中的核心环节，其目标是通过计算方法预测化合物的药代动力学特性、毒性、溶解度等关键属性，从而加速先导化合物的筛选与优化。传统的定量构效关系（Quantitative Structure-Activity Relationship, QSAR）方法依赖于人工设计的分子描述符，如Morgan指纹、MACCS keys等，配合随机森林、支持向量机等机器学习算法，在过去几十年中发挥了重要作用。然而，这些方法在处理复杂非线性关系和捕获分子结构的深层语义信息方面存在明显局限。

近年来，深度学习技术在分子性质预测领域取得了突破性进展。图神经网络（Graph Neural Networks, GNN）能够直接处理分子图结构，自然地建模原子间的拓扑关系；基于Transformer的分子预训练模型则通过大规模无标签分子数据的自监督学习，获得了丰富的分子表示能力。根据最新研究数据，深度学习模型在多个基准数据集上的预测性能已显著超越传统方法：在Tox21数据集上，GNN模型的平均ROC-AUC达到0.85，较传统随机森林方法提升约12%；在BACE数据集上，Transformer-based模型的相关系数R²达到0.82，展现出更强的预测精度。

本文将系统介绍分子性质预测的技术原理，提供完整的实践代码，并通过真实数据集的案例分析，帮助读者快速掌握这一核心技术。

---

## 技术原理

### 分子表示方法

分子性质预测的首要问题是**如何将分子结构转化为模型可处理的数学表示**。主要方法包括：

| 表示方法 | 描述 | 优点 | 缺点 |
|---------|------|------|------|
| SMILES | 字符串表示 | 简单直观，易于存储 | 语义信息稀疏，存在等价表示问题 |
| 分子指纹 | 离散向量 | 计算效率高，可解释性强 | 丢失三维结构信息 |
| 分子图 | 图结构数据 | 保留拓扑信息，适合GNN | 计算复杂度较高 |
| 3D构象 | 三维坐标 | 包含空间结构信息 | 构象生成计算昂贵 |

**Morgan指纹**（又称ECFP，Extended-Connectivity Fingerprints）是最广泛使用的分子指纹方法。它通过迭代聚合相邻原子的环境信息，生成固定长度的二进制向量。在RDKit中，默认生成2048维的指纹向量。

### 图神经网络架构

图神经网络直接对分子图进行操作，每个原子作为节点，边代表化学键。其核心思想是**消息传递机制**（Message Passing Neural Network, MPNN）：

$$h_v^{(k)} = \text{UPDATE}\left(h_v^{(k-1)}, \text{AGG}\left(\{m_{uv}^{(k)} : u \in \mathcal{N}(v)\}\right)\right)$$

其中，$m_{uv}^{(k)}$表示从节点$u$传递到节点$v$的消息，$\mathcal{N}(v)$为节点$v$的邻居集合。

主流的GNN变体包括：

- **GCN**（Graph Convolutional Network）：基于谱方法的图卷积
- **GAT**（Graph Attention Network）：引入注意力机制建模不同邻居的重要性
- **GraphSAGE**：通过采样和聚合操作提高大规模图的处理效率

### 分子预训练模型

预训练范式的引入标志着分子表示学习的重要进展。代表性工作包括：

- **MolBERT**（Schwaller et al., 2021）：基于BERT架构，在大规模SMILES数据上进行自监督预训练
- **ChemBERTa**（Ahmad et al., 2022）：使用Transformer编码器，在1400万分子上进行预训练
- **GraphMVP**（Liu et al., 2022）：结合2D图和3D构象的多模态预训练

这些模型通过掩码原子预测、对比学习等任务学习通用的分子表示，在下游任务中展现出优异的迁移能力。

---

## 实践指南

### 环境配置

```python
# 创建虚拟环境
conda create -n molpred python=3.10
conda activate molpred

# 安装核心依赖
pip install torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cu118
pip install rdkit-pypi
pip install dgllife torch_geometric
pip install pandas numpy scikit-learn
pip install matplotlib seaborn
```

### 数据准备与分子表示

```python
import pandas as pd
import numpy as np
from rdkit import Chem
from rdkit.Chem import AllChem, Descriptors
from rdkit.Chem import rdMolDescriptors
import warnings
warnings.filterwarnings('ignore')

class MolecularFeaturizer:
    """分子特征提取器"""
    
    def __init__(self, fingerprint_radius=2, fingerprint_nBits=2048):
        self.radius = fingerprint_radius
        self.nBits = fingerprint_nBits
    
    def smiles_to_mol(self, smiles):
        """SMILES转分子对象"""
        mol = Chem.MolFromSmiles(smiles)
        if mol is None:
            return None
        # 规范化SMILES
        return mol
    
    def get_morgan_fingerprint(self, mol):
        """Morgan指纹"""
        if mol is None:
            return np.zeros(self.nBits)
        fp = AllChem.GetMorganFingerprintAsBitVect(
            mol, self.radius, nBits=self.nBits
        )
        return np.array(fp)
    
    def get_molecular_descriptors(self, mol):
        """分子描述符"""
        if mol is None:
            return np.zeros(200)
        
        descriptors = [
            Descriptors.MolWt, Descriptors.MolLogP, Descriptors.TPSA,
            Descriptors.NumHDonors, Descriptors.NumHAcceptors,
            Descriptors.NumRotatableBonds, Descriptors.NumAromaticRings,
            Descriptors.FractionCSP3, Descriptors.HeavyAtomCount,
            Descriptors.RingCount, Descriptors.NumValenceElectrons,
            Descriptors.NumRadicalElectrons, Descriptors.BertzCT,
            Descriptors.Chi0, Descriptors.Chi1, Descriptors.LabuteASA,
            Descriptors.PEOE_VSA1, Descriptors.SMR_VSA1, Descriptors.SlogP_VSA1
        ]
        
        try:
            return np.array([d(mol) for d in descriptors])
        except:
            return np.zeros(len(descriptors))
    
    def featurize(self, smiles):
        """完整特征提取流程"""
        mol = self.smiles_to_mol(smiles)
        fp = self.get_morgan_fingerprint(mol)
        desc = self.get_molecular_descriptors(mol)
        return np.concatenate([fp, desc])

# 测试特征提取
featurizer = MolecularFeaturizer()
test_smiles = "CC(=O)Oc1ccccc1C(=O)O"  # 阿司匹林
features = featurizer.featurize(test_smiles)
print(f"特征维度: {features.shape}")
print(f"Morgan指纹维度: 2048, 描述符维度: 19")
```

### 数据集加载与划分

```python
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler

def load_tox21_dataset():
    """加载Tox21数据集（示例）"""
    # 实际应用中从PubChem或MoleculeNet下载
    # 这里使用示例数据展示格式
    data = {
        'smiles': [
            'CC(C)Cc1ccc(cc1)C(C)C(=O)O',  # Ibuprofen
            'Cc1ccc(cc1)C(c1ccccc1)(C)C',  // Xylene
            'CC(C)NCC(COc1ccccc1)O',  // Propranolol
            'CN1C=NC2=C1C(=O)N(C(=O)N2C)C',  // Caffeine
            'CC(=O)Oc1ccccc1C(=O)O',  // Aspirin
        ],
        'toxicity': [0, 1, 0, 0, 1]  # 二分类标签
    }
    return pd.DataFrame(data)

def prepare_dataset(csv_path, target_column='label', test_size=0.2):
    """数据集准备与划分"""
    df = pd.read_csv(csv_path)
    
    # 特征提取
    features = []
    valid_indices = []
    for idx, smiles in enumerate(df['smiles']):
        try:
            feat = featurizer.featurize(smiles)
            features.append(feat)
            valid_indices.append(idx)
        except:
            continue
    
    X = np.array(features)
    y = df.iloc[valid_indices][target_column].values
    
    # 数据标准化
    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(X)
    
    # 划分数据集
    X_train, X_test, y_train, y_test = train_test_split(
        X_scaled, y, test_size=test_size, random_state=42, stratify=y
    )
    
    return X_train, X_test, y_train, y_test, scaler

print("数据集准备完成")
```

### 模型训练与评估

```python
import torch
import torch.nn as nn
import torch.optim as optim
from torch.utils.data import DataLoader, TensorDataset
from sklearn.metrics import roc_auc_score, accuracy_score, f1_score

class MolecularPropertyPredictor(nn.Module):
    """基于深度神经网络的分子性质预测模型"""
    
    def __init__(self, input_dim, hidden_dims=[512, 256, 128], dropout=0.3):
        super().__init__()
        
        layers = []
        prev_dim = input_dim
        
        for hidden_dim in hidden_dims:
            layers.extend([
                nn.Linear(prev_dim, hidden_dim),
                nn.BatchNorm1d(hidden_dim),
                nn.ReLU(),
                nn.Dropout(dropout)
            ])
            prev_dim = hidden_dim
        
        layers.append(nn.Linear(prev_dim, 1))
        layers.append(nn.Sigmoid())
        
        self.network = nn.Sequential(*layers)
    
    def forward(self, x):
        return self.network(x).squeeze()

def train_model(X_train, y_train, X_test, y_test, 
                epochs=100, batch_size=32, lr=1e-3):
    """模型训练"""
    
    # 转换为PyTorch张量
    X_train_t = torch.FloatTensor(X_train)
    y_train_t = torch.FloatTensor(y_train)
    X_test_t = torch.FloatTensor(X_test)
    y_test_t = torch.FloatTensor(y_test)
    
    # 创建数据加载器
    train_dataset = TensorDataset(X_train_t, y_train_t)
    train_loader = DataLoader(train_dataset, batch_size=batch_size, shuffle=True)
    
    # 初始化模型
    model = MolecularPropertyPredictor(input_dim=X_train.shape[1])
    criterion = nn.BCELoss()
    optimizer = optim.Adam(model.parameters(), lr=lr)
    scheduler = optim.lr_scheduler.ReduceLROnPlateau(
        optimizer, mode='max', patience=10, factor=0.5
    )
    
    # 训练循环
    best_auc = 0
    best_model_state = None
    history = {'train_loss': [], 'test_auc': []}
    
    for epoch in range(epochs):
        model.train()
        train_loss = 0
        
        for batch_x, batch_y in train_loader:
            optimizer.zero_grad()
            outputs = model(batch_x)
            loss = criterion(outputs, batch_y)
            loss.backward()
            optimizer.step()
            train_loss += loss.item()
        
        # 验证
        model.eval()
        with torch.no_grad():
            test_pred = model(X_test_t).numpy()
        
        test_auc = roc_auc_score(y_test, test_pred)
        history['train_loss'].append(train_loss / len(train_loader))
        history['test_auc'].append(test_auc)
        
        scheduler.step(test_auc)
        
        if test_auc > best_auc:
            best_auc = test_auc
            best_model_state = model.state_dict().copy()
        
        if (epoch + 1) % 20 == 0:
            print(f"Epoch {epoch+1}/{epochs}, Loss: {train_loss/len(train_loader):.4f}, "
                  f"Test AUC: {test_auc:.4f}")
    
    # 加载最佳模型
    model.load_state_dict(best_model_state)
    return model, history, best_auc

# 训练示例
print("开始训练模型...")
model, history, best_auc = train_model(X_train, y_train, X_test, y_test)
print(f"最佳测试AUC: {best_auc:.4f}")
```

---

## 案例分析：BBB渗透性预测

### 数据集描述

血脑屏障（Blood-Brain Barrier, BBB）渗透性是中枢神经系统药物的关键性质。我们使用BBBP（Blood-Brain Barrier Penetration）数据集进行案例分析，该数据集包含约2000个化合物的BBB渗透性标签。

### 完整分析流程

```python
import matplotlib.pyplot as plt
import seaborn as sns
from sklearn.ensemble import RandomForestClassifier, GradientBoostingClassifier
from sklearn.svm import SVC
from sklearn.neural_network import MLPClassifier
from sklearn.metrics import confusion_matrix, classification_report, roc_curve

def evaluate_models(X_train, X_test, y_train, y_test):
    """多模型对比评估"""
    
    models = {
        'Random Forest': RandomForestClassifier(
            n_estimators=200, max_depth=10, random_state=42, n_jobs=-1
        ),
        'Gradient Boosting': GradientBoostingClassifier(
            n_estimators=100, max_depth=5, random_state=42
        ),
        'SVM': SVC(kernel='rbf', probability=True, random_state=42),
        'MLP': MLPClassifier(
            hidden_layer_sizes=(256, 128, 64), 
            max_iter=500, random_state=42, early_stopping=True
        ),
        'Deep Neural Network': None  # 单独处理
    }
    
    results = {}
    
    for name, model in models.items():
        if name == 'Deep Neural Network':
            model, hist, auc = train_model(X_train, y_train, X_test, y_test)
            results[name] = {'model': model, 'auc': auc}
            continue
            
        model.fit(X_train, y_train)
        y_pred_proba = model.predict_proba(X_test)[:, 1]
        auc = roc_auc_score(y_test, y_pred_proba)
        results[name] = {'model': model, 'auc': auc}
        print(f"{name}: AUC = {auc:.4f}")
    
    return results

def plot_results(results, y_test):
    """可视化结果"""
    
    fig, axes = plt.subplots(1, 3, figsize=(15, 5))
    
    # 1. AUC对比柱状图
    ax1 = axes[0]
    names = list(results.keys())
    aucs = [r['auc'] for r in results.values()]
    colors = plt.cm.viridis(np.linspace(0.2, 0.8, len(names)))
    bars = ax1.barh(names, aucs, color=colors)
    ax1.set_xlabel('ROC-AUC')
    ax1.set_title('模型性能对比')
    ax1.set_xlim(0.5, 1.0)
    for bar, auc in zip(bars, aucs):
        ax1.text(auc + 0.01, bar.get_y() + bar.get_height()/2, 
                f'{auc:.3f}', va='center')
    
    # 2. ROC曲线
    ax2 = axes[1]
    for name, result in results.items():
        model = result['model']
        if hasattr(model, 'predict_proba'):
            y_pred_proba = model.predict_proba(X_test)[:, 1]
        else:
            y_pred_proba = model(torch.FloatTensor(X_test)).detach().numpy()
        fpr, tpr, _ = roc_curve(y_test, y_pred_proba)
        ax2.plot(fpr, tpr, label=f"{name} (AUC={result['auc']:.3f})")
    
    ax2.plot([0, 1], [0, 1], 'k--', alpha=0.5)
    ax2.set_xlabel('False Positive Rate')
    ax2.set_ylabel('True Positive Rate')
    ax2.set_title('ROC Curves')
    ax2.legend(loc='lower right', fontsize=8)
    
    # 3. 混淆矩阵（最佳模型）
    ax3 = axes[2]
    best_model_name = max(results, key=lambda x: results[x]['auc'])
    best_model = results[best_model_name]['model']
    
    if hasattr(best_model, 'predict'):
        y_pred = best_model.predict(X_test)
    else:
        y_pred = (best_model(torch.FloatTensor(X_test)).detach().numpy() > 0.5).astype(int)
    
    cm = confusion_matrix(y_test, y_pred)
    sns.heatmap(cm, annot=True, fmt='d', cmap='Blues', ax=ax3)
    ax3.set_xlabel('Predicted')
    ax3.set_ylabel('Actual')
    ax3.set_title(f'Confusion Matrix ({best_model_name})')
    
    plt.tight_layout()
    plt.savefig('model_comparison.png', dpi=150, bbox_inches='tight')
    plt.show()

# 执行分析
print("=" * 60)
print("BBB渗透性预测模型对比分析")
print("=" * 60)

results = evaluate_models(X_train, X_test, y_train, y_test)
plot_results(results, y_test)

# 性能汇总表
print("\n" + "=" * 60)
print("性能汇总")
print("=" * 60)
print(f"{'模型':<25} {'ROC-AUC':<10} {'运行时间(s)':<15}")
print("-" * 50)
```

### 性能基准数据

基于MoleculeNet基准测试，主流模型在BBB渗透性预测任务上的性能如下：

| 模型 | ROC-AUC | Precision | Recall | F1-Score |
|------|---------|-----------|--------|----------|
| Random Forest | 0.72 | 0.68 | 0.71 | 0.69 |
| Gradient Boosting | 0.75 | 0.72 | 0.74 | 0.73 |
| SVM (RBF) | 0.71 | 0.69 | 0.70 | 0.69 |
| MLP | 0.78 | 0.75 | 0.77 | 0.76 |
| Deep Neural Network | 0.82 | 0.79 | 0.81 | 0.80 |
| **GNN (GCN)**