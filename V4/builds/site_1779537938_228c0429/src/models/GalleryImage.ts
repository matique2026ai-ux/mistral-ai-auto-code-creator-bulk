import { DataTypes, Model } from 'sequelize';
import sequelize from '../config/database';

class GalleryImage extends Model {
  public id!: number;
  public image_url!: string;
  public description!: string;
  public readonly created_at!: Date;
}

GalleryImage.init({
  id: {
    type: DataTypes.INTEGER,
    autoIncrement: true,
    primaryKey: true
  },
  image_url: {
    type: DataTypes.STRING(255),
    allowNull: false
  },
  description: {
    type: DataTypes.TEXT,
    allowNull: true
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: false,
    defaultValue: DataTypes.NOW
  }
}, {
  sequelize,
  modelName: 'GalleryImage',
  tableName: 'gallery_images',
  timestamps: false
});

export default GalleryImage;