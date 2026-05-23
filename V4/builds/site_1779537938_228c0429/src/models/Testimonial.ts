import { DataTypes, Model } from 'sequelize';
import sequelize from '../config/database';

class Testimonial extends Model {
  public id!: number;
  public name!: string;
  public comment!: string;
  public rating!: number;
  public readonly created_at!: Date;
}

Testimonial.init({
  id: {
    type: DataTypes.INTEGER,
    autoIncrement: true,
    primaryKey: true
  },
  name: {
    type: DataTypes.STRING(255),
    allowNull: false
  },
  comment: {
    type: DataTypes.TEXT,
    allowNull: false
  },
  rating: {
    type: DataTypes.INTEGER,
    allowNull: false,
    validate: {
      min: 1,
      max: 5
    }
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: false,
    defaultValue: DataTypes.NOW
  }
}, {
  sequelize,
  modelName: 'Testimonial',
  tableName: 'testimonials',
  timestamps: false
});

export default Testimonial;