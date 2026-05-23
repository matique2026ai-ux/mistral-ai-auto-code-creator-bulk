import { Request, Response } from 'express';
import { MenuItem } from '../models/MenuItem';
import logger from '../utils/logger';

class MenuController {
  static async getMenuItems(req: Request, res: Response) {
    try {
      const category = req.query.category as string;
      
      let whereClause = {};
      
      if (category) {
        whereClause = { category };
      }
      
      const menuItems = await MenuItem.findAll({ where: whereClause });
      
      res.json(menuItems);
    } catch (error) {
      logger.error('Get menu items error:', error);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
  
  static async getMenuItemById(req: Request, res: Response) {
    try {
      const menuItemId = req.params.id;
      
      const menuItem = await MenuItem.findByPk(menuItemId);
      
      if (!menuItem) {
        return res.status(404).json({ error: 'Menu item not found' });
      }
      
      res.json(menuItem);
    } catch (error) {
      logger.error('Get menu item by ID error:', error);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
}

export default MenuController;