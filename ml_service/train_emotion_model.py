"""
Train a custom emotion detection model on conversation data
This solves the negation problem by learning from actual examples

The problem: Keyword matching can't handle negation
- "not feeling good" finds 'good' → returns 'joy' (WRONG)
- "I can't feel happy" finds 'happy' → returns 'joy' (WRONG)

The solution: Train a transformer model on conversation examples
that includes negation patterns, so it learns context and meaning
"""

import torch
import numpy as np
import json
import sys
from pathlib import Path

try:
    from transformers import AutoTokenizer, AutoModelForSequenceClassification, TextClassificationPipeline
    from transformers import Trainer, TrainingArguments
except ImportError as e:
    print(f"[ERROR] Missing transformers: {e}")
    sys.exit(1)

try:
    from datasets import Dataset
except ImportError:
    print("[ERROR] Missing datasets package. Install: pip install datasets")
    sys.exit(1)

try:
    import pandas as pd
except ImportError:
    print("[ERROR] Missing pandas package. Install: pip install pandas")
    sys.exit(1)

try:
    from sklearn.model_selection import train_test_split
except ImportError:
    print("[ERROR] Missing scikit-learn package. Install: pip install scikit-learn")
    sys.exit(1)

# Emotion labels
EMOTION_LABELS = {
    'sadness': 0,
    'joy': 1,
    'anxiety': 2,
    'anger': 3,
    'neutral': 4,
    'grief': 5,
}

LABEL_TO_EMOTION = {v: k for k, v in EMOTION_LABELS.items()}

class EmotionModelTrainer:
    """Train custom emotion detection model"""
    
    def __init__(self, model_name='distilroberta-base', output_dir='./emotion_model'):
        """Initialize trainer"""
        self.model_name = model_name
        self.output_dir = Path(output_dir)
        self.output_dir.mkdir(exist_ok=True)
        
        print(f"[INIT] Loading tokenizer and model: {model_name}")
        self.tokenizer = AutoTokenizer.from_pretrained(model_name)
        self.model = AutoModelForSequenceClassification.from_pretrained(
            model_name, 
            num_labels=len(EMOTION_LABELS)
        )
        print(f"[OK] Model loaded with {len(EMOTION_LABELS)} emotion labels")
        
    def load_training_data(self, jsonl_file: str) -> pd.DataFrame:
        """Load conversation data from JSONL file"""
        print(f"\n[LOAD] Reading training data from {jsonl_file}")
        
        data = []
        with open(jsonl_file, 'r', encoding='utf-8') as f:
            for i, line in enumerate(f):
                try:
                    example = json.loads(line)
                    data.append(example)
                    if (i + 1) % 100 == 0:
                        print(f"  Loaded {i + 1} examples...")
                except json.JSONDecodeError:
                    continue
        
        df = pd.DataFrame(data)
        print(f"[OK] Loaded {len(df)} training examples")
        print(f"  Columns: {list(df.columns)}")
        print(f"  Emotions: {df['emotion'].value_counts().to_dict() if 'emotion' in df.columns else 'N/A'}")
        return df
    
    def prepare_dataset(self, df: pd.DataFrame):
        """Prepare dataset for training"""
        print(f"\n[PREP] Preparing dataset")
        
        # Validate required columns
        if 'text' not in df.columns or 'emotion' not in df.columns:
            raise ValueError("Dataset must have 'text' and 'emotion' columns")
        
        # Remove null values
        df = df.dropna(subset=['text', 'emotion'])
        print(f"  After removing nulls: {len(df)} examples")
        
        # Convert emotion labels to indices
        df['label'] = df['emotion'].map(EMOTION_LABELS)
        
        # Handle unknown emotions
        if df['label'].isnull().any():
            unknown_emotions = df[df['label'].isnull()]['emotion'].unique()
            print(f"  WARNING: Unknown emotions found: {unknown_emotions}")
            df = df.dropna(subset=['label'])
            print(f"  After removing unknown emotions: {len(df)} examples")
        
        df['label'] = df['label'].astype(int)
        
        # Split data
        train_df, val_df = train_test_split(df, test_size=0.2, random_state=42, stratify=df['label'])
        print(f"  Train set: {len(train_df)} examples")
        print(f"  Val set: {len(val_df)} examples")
        
        # Convert to HuggingFace Dataset
        train_dataset = Dataset.from_pandas(train_df[['text', 'label']])
        val_dataset = Dataset.from_pandas(val_df[['text', 'label']])
        
        # Tokenize
        def tokenize_function(examples):
            return self.tokenizer(
                examples['text'],
                padding='max_length',
                truncation=True,
                max_length=128
            )
        
        print(f"  Tokenizing data...")
        train_dataset = train_dataset.map(tokenize_function, batched=True)
        val_dataset = val_dataset.map(tokenize_function, batched=True)
        
        return train_dataset, val_dataset
    
    def train(self, train_dataset, val_dataset, epochs=3, batch_size=16):
        """Train the model"""
        print(f"\n[TRAIN] Starting training")
        print(f"  Epochs: {epochs}")
        print(f"  Batch size: {batch_size}")
        
        from transformers import Trainer, TrainingArguments
        
        training_args = TrainingArguments(
            output_dir=str(self.output_dir),
            num_train_epochs=epochs,
            per_device_train_batch_size=batch_size,
            per_device_eval_batch_size=batch_size,
            warmup_steps=100,
            weight_decay=0.01,
            logging_dir='./logs',
            logging_steps=50,
            eval_strategy='epoch',
            save_strategy='epoch',
            load_best_model_at_end=True,
        )
        
        trainer = Trainer(
            model=self.model,
            args=training_args,
            train_dataset=train_dataset,
            eval_dataset=val_dataset,
        )
        
        trainer.train()
        print(f"[OK] Training complete!")
        
        # Save model
        model_path = self.output_dir / 'final'
        self.model.save_pretrained(model_path)
        self.tokenizer.save_pretrained(model_path)
        print(f"[SAVE] Model saved to {model_path}")
        
        return model_path
    
    def evaluate(self, val_dataset):
        """Evaluate model on validation set"""
        print(f"\n[EVAL] Evaluating model")
        from transformers import Trainer, TrainingArguments
        
        training_args = TrainingArguments(output_dir=str(self.output_dir))
        trainer = Trainer(
            model=self.model,
            args=training_args,
            eval_dataset=val_dataset,
        )
        
        results = trainer.evaluate()
        accuracy = results.get('eval_accuracy')
        loss = results.get('eval_loss')
        
        if accuracy is not None:
            print(f"  Accuracy: {accuracy:.4f}")
        else:
            print(f"  Accuracy: N/A")
            
        if loss is not None:
            print(f"  Loss: {loss:.4f}")
        else:
            print(f"  Loss: N/A")
        
        return results


def main():
    """Main training pipeline"""
    print("\n" + "="*60)
    print("EMOTION DETECTION MODEL TRAINING")
    print("Solving negation detection problem with real ML")
    print("="*60)
    
    # Check for training data
    training_file = Path('./conversation_training_data.jsonl')
    if not training_file.exists():
        print(f"\n[ERROR] Training data not found: {training_file}")
        print(f"[INFO] Create training data using: generate_training_data.php")
        sys.exit(1)
    
    # Initialize trainer
    trainer = EmotionModelTrainer(
        model_name='distilroberta-base',
        output_dir='./emotion_model'
    )
    
    # Load and prepare data
    df = trainer.load_training_data(str(training_file))
    train_dataset, val_dataset = trainer.prepare_dataset(df)
    
    # Train model
    model_path = trainer.train(train_dataset, val_dataset, epochs=3, batch_size=16)
    
    # Evaluate
    trainer.evaluate(val_dataset)
    
    print(f"\n[SUCCESS] Model training complete!")
    print(f"[NEXT] Update unified_ml_api.py to use: {model_path}")
    
    return model_path


if __name__ == '__main__':
    main()

